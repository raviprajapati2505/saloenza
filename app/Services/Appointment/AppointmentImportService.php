<?php

namespace App\Services\Appointment;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\SalonServiceProduct;
use App\Models\User;
use App\Services\Customer\CustomerClvService;
use App\Services\NoShow\NoShowPolicyService;
use App\Support\Appointment\AppointmentPayment;
use App\Support\Appointment\AppointmentStatus;
use App\Support\NoShow\DepositStatus;
use App\Support\Phone\PhoneNumber;
use App\Support\Tenant\TenantConfig;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AppointmentImportService
{
    public const CACHE_PREFIX = 'appointment-import:';

    /** @var list<string> */
    public const HEADERS = [
        'appointment_code',
        'branch_name',
        'staff_phone',
        'service_name',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_whatsapp',
        'customer_birthday',
        'customer_anniversary',
        'customer_notes',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'line_price',
        'status',
        'type',
        'discount',
        'grand_total',
        'payment_status',
        'payment_method',
        'amount_paid',
        'paid_at',
        'invoice_number',
        'notes',
    ];

    /** @var list<string> */
    private const TERMINAL_STATUSES = [
        AppointmentStatus::COMPLETED,
        AppointmentStatus::CANCELLED,
        AppointmentStatus::NO_SHOW,
    ];

    /** @var list<string> */
    private const VISIT_FIELDS = [
        'branch_name',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_whatsapp',
        'customer_birthday',
        'customer_anniversary',
        'customer_notes',
        'status',
        'type',
        'discount',
        'grand_total',
        'payment_status',
        'payment_method',
        'amount_paid',
        'paid_at',
        'invoice_number',
        'notes',
    ];

    public function __construct(
        private readonly AppointmentBookingService $booking,
        private readonly AppointmentNotificationService $notifications,
        private readonly NoShowPolicyService $noShowPolicies,
        private readonly CustomerClvService $customerValues,
        private readonly TenantConfig $tenantConfig,
    ) {}

    public function sampleCsv(): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::HEADERS);
        fputcsv($handle, [
            'APT-1001',
            'Main Branch',
            '+919800000001',
            'Haircut',
            'Asha Patel',
            '+919811110001',
            'asha@example.com',
            '+919811110001',
            '1992-04-12',
            '',
            'Prefers morning slots',
            '2026-03-01 10:00',
            '2026-03-01 10:45',
            '45',
            '800',
            'completed',
            'appointment',
            '0',
            '1500',
            'paid',
            'upi',
            '1500',
            '2026-03-01 11:00',
            'INV-1001',
            'Imported visit',
        ]);
        fputcsv($handle, [
            'APT-1001',
            'Main Branch',
            '+919800000001',
            'Facial',
            'Asha Patel',
            '+919811110001',
            'asha@example.com',
            '+919811110001',
            '1992-04-12',
            '',
            'Prefers morning slots',
            '2026-03-01 10:45',
            '2026-03-01 11:30',
            '45',
            '700',
            'completed',
            'appointment',
            '0',
            '1500',
            'paid',
            'upi',
            '1500',
            '2026-03-01 11:00',
            'INV-1001',
            'Imported visit',
        ]);
        fputcsv($handle, [
            'APT-1002',
            'Main Branch',
            '+919800000001',
            'Haircut',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '2026-03-02 16:00',
            '2026-03-02 16:45',
            '45',
            '800',
            'completed',
            'walk_in',
            '0',
            '800',
            'paid',
            'cash',
            '800',
            '2026-03-02 16:45',
            '',
            'Walk-in',
        ]);
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(User $actor, Saloon $saloon, string $contents): array
    {
        $parsed = $this->parse($contents);
        $grouped = $this->groupRows($parsed['rows']);
        $errors = $parsed['errors'];
        $valid = [];

        foreach ($grouped['invalid'] as $error) {
            $errors[] = $error;
        }

        foreach ($grouped['groups'] as $group) {
            $groupErrors = $this->validateGroup($saloon, $group);
            if ($groupErrors !== []) {
                array_push($errors, ...$groupErrors);

                continue;
            }

            $valid[] = $group;
        }

        usort($errors, fn (array $left, array $right): int => ($left['row'] ?? 0) <=> ($right['row'] ?? 0));

        $token = null;
        if ($valid !== []) {
            $token = (string) Str::uuid();
            Cache::put(self::CACHE_PREFIX.$token, [
                'user_id' => (int) $actor->id,
                'saloon_id' => (int) $saloon->id,
                'groups' => $valid,
                'cursor' => 0,
            ], now()->addHours(2));
        }

        return [
            'token' => $token,
            'salon' => [
                'id' => (int) $saloon->id,
                'name' => $saloon->name,
            ],
            'total_rows' => count($parsed['rows']) + count($parsed['errors']),
            'appointment_count' => count($grouped['groups']),
            'valid_count' => count($valid),
            'invalid_count' => count($grouped['groups']) - count($valid),
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function commitBatch(User $actor, Saloon $saloon, string $token, int $limit): array
    {
        $key = self::CACHE_PREFIX.$token;
        $state = Cache::get($key);

        if (! is_array($state)) {
            throw ValidationException::withMessages([
                'token' => 'This import preview expired. Upload the file again.',
            ]);
        }

        if ((int) ($state['user_id'] ?? 0) !== (int) $actor->id || (int) ($state['saloon_id'] ?? 0) !== (int) $saloon->id) {
            throw ValidationException::withMessages([
                'token' => 'This import preview does not belong to the selected salon.',
            ]);
        }

        $groups = array_values($state['groups'] ?? []);
        $cursor = (int) ($state['cursor'] ?? 0);
        $limit = max(1, min($limit, 50));
        $slice = array_slice($groups, $cursor, $limit);
        $results = [];
        $customerIds = [];

        foreach ($slice as $group) {
            $result = $this->importGroup($actor, $saloon, $group);
            $results[] = $result;
            if (! empty($result['customer_id'])) {
                $customerIds[] = (int) $result['customer_id'];
            }
            $cursor++;
            $state['cursor'] = $cursor;
            Cache::put($key, $state, now()->addHours(2));
        }

        foreach (array_unique($customerIds) as $customerId) {
            try {
                $this->customerValues->recomputeCustomer($customerId, (int) $saloon->id);
            } catch (Throwable) {
                // The visit is already stored. Customer value can be recomputed later.
            }
        }

        $total = count($groups);

        return [
            'processed' => $cursor,
            'total' => $total,
            'remaining' => max($total - $cursor, 0),
            'done' => $cursor >= $total,
            'results' => $results,
        ];
    }

    /**
     * @param  array{appointment_code: string, rows: list<array{row: int, data: array<string, string>}>}  $group
     * @return array<string, mixed>
     */
    private function importGroup(User $actor, Saloon $saloon, array $group): array
    {
        $rows = $group['rows'];
        $rowNumbers = array_map(fn (array $row): int => (int) $row['row'], $rows);
        $code = $group['appointment_code'];

        try {
            $appointment = DB::transaction(function () use ($actor, $saloon, $group): Appointment {
                $payload = $this->buildPayload($saloon, $group);
                $customerId = $this->resolveCustomer($saloon, $payload['customer'], $actor);
                unset($payload['customer']);

                $existing = Appointment::query()
                    ->where('saloon_id', $saloon->id)
                    ->where('import_key', $payload['import_key'])
                    ->exists();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'appointment_code' => "Appointment {$payload['import_key']} was already imported.",
                    ]);
                }

                $payload['customer_id'] = $customerId;
                $appointment = $this->booking->create($actor, $payload);

                $updates = [
                    'import_key' => $payload['import_key'],
                    'invoice_number' => $payload['invoice_number'] ?? null,
                    'booking_source' => Appointment::SOURCE_IMPORT,
                ];

                if (in_array($appointment->status, self::TERMINAL_STATUSES, true)) {
                    $appointment->deposit_required_amount = null;
                    $appointment->deposit_status = DepositStatus::NOT_REQUIRED;
                    $appointment->deposit_paid_at = null;
                    $updates['deposit_required_amount'] = null;
                    $updates['deposit_status'] = DepositStatus::NOT_REQUIRED;
                    $updates['deposit_paid_at'] = null;
                }

                if ($appointment->status === AppointmentStatus::NO_SHOW) {
                    $updates = array_merge($updates, $this->noShowPolicies->feeFieldsForNoShow($appointment));
                }

                $payment = $payload['payment_snapshot'] ?? [];
                if ($payment !== []) {
                    $updates = array_merge($updates, $payment);
                }

                $appointment->forceFill($updates)->save();

                return $appointment->fresh();
            });

            $this->notifyIfLiveBooking($appointment);

            return [
                'appointment_code' => $code,
                'inserted' => true,
                'appointment_id' => (int) $appointment->id,
                'customer_id' => $appointment->customer_id !== null ? (int) $appointment->customer_id : null,
                'rows' => $rowNumbers,
                'message' => null,
            ];
        } catch (ValidationException $exception) {
            return $this->failedResult($code, $rowNumbers, $this->exceptionMessage($exception));
        } catch (AuthorizationException $exception) {
            return $this->failedResult($code, $rowNumbers, $exception->getMessage());
        } catch (Throwable) {
            return $this->failedResult($code, $rowNumbers, 'This visit could not be imported.');
        }
    }

    private function notifyIfLiveBooking(Appointment $appointment): void
    {
        $startsAt = $appointment->starts_at;
        $isUpcoming = $startsAt !== null
            && $startsAt->isFuture()
            && in_array($appointment->status, [AppointmentStatus::SCHEDULED, AppointmentStatus::CONFIRMED], true);

        if (! $isUpcoming) {
            return;
        }

        try {
            $this->notifications->appointmentCreated($appointment);
        } catch (Throwable) {
            // The visit is saved. A mail failure should not roll the import back.
        }
    }

    /**
     * @param  list<int>  $rowNumbers
     * @return array<string, mixed>
     */
    private function failedResult(string $code, array $rowNumbers, string $message): array
    {
        return [
            'appointment_code' => $code,
            'inserted' => false,
            'appointment_id' => null,
            'customer_id' => null,
            'rows' => $rowNumbers,
            'message' => $message,
        ];
    }

    /**
     * @param  array{appointment_code: string, rows: list<array{row: int, data: array<string, string>}>}  $group
     * @return array<string, mixed>
     */
    private function buildPayload(Saloon $saloon, array $group): array
    {
        $errors = $this->validateGroup($saloon, $group);
        if ($errors !== []) {
            throw ValidationException::withMessages([
                'file' => $errors[0]['message'],
            ]);
        }

        $header = $group['rows'][0]['data'];
        $timezone = (string) $this->tenantConfig->get($saloon, 'regional', 'timezone', 'Asia/Kolkata');
        $branch = $this->findBranch($saloon, $header['branch_name']);
        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch_name' => "Branch \"{$header['branch_name']}\" was not found. Add it in the app before importing.",
            ]);
        }

        $lines = [];
        $earliest = null;

        foreach ($group['rows'] as $row) {
            $data = $row['data'];
            $staff = $this->findStaff($saloon, $data['staff_phone']);
            $offering = $this->findOffering($saloon, (int) $branch->id, $data['service_name']);
            $startsAt = $this->parseDateTime($data['starts_at'], $timezone);
            $endsAt = $this->parseDateTime($data['ends_at'], $timezone);
            $earliest = $earliest === null || $startsAt->lt($earliest) ? $startsAt->copy() : $earliest;

            $lines[] = [
                'service_id' => (int) $offering->service_id,
                'product_id' => $offering->product_id !== null ? (int) $offering->product_id : null,
                'staff_id' => (int) $staff->id,
                'price' => round((float) $data['line_price'], 2),
                'duration_minutes' => (int) $data['duration_minutes'],
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ];
        }

        $status = strtolower($header['status']);
        $type = $header['type'] !== '' ? strtolower($header['type']) : Appointment::TYPE_APPOINTMENT;
        $discount = round((float) ($header['discount'] !== '' ? $header['discount'] : 0), 2);
        $grandTotal = round((float) $header['grand_total'], 2);
        $paymentStatus = strtolower($header['payment_status']);
        $amountPaid = round((float) ($header['amount_paid'] !== '' ? $header['amount_paid'] : 0), 2);

        $payload = [
            'saloon_id' => (int) $saloon->id,
            'branch_id' => (int) $branch->id,
            'starts_at' => $earliest?->toIso8601String(),
            'status' => $status,
            'type' => $type,
            'discount' => $discount,
            'notes' => $header['notes'] !== '' ? $header['notes'] : null,
            'booking_source' => Appointment::SOURCE_IMPORT,
            'services' => $lines,
            'products' => [],
            'import_key' => $group['appointment_code'],
            'invoice_number' => $header['invoice_number'] !== '' ? $header['invoice_number'] : null,
            'customer' => [
                'name' => $header['customer_name'],
                'phone' => $header['customer_phone'],
                'email' => $header['customer_email'],
                'whatsapp' => $header['customer_whatsapp'],
                'birthday' => $header['customer_birthday'],
                'anniversary' => $header['customer_anniversary'],
                'notes' => $header['customer_notes'],
            ],
            'payment_snapshot' => $this->paymentSnapshot($header, $timezone, $paymentStatus, $amountPaid),
        ];

        if (in_array($status, self::TERMINAL_STATUSES, true)) {
            $payload['skip_staff_availability'] = true;
        }

        if (in_array($paymentStatus, [AppointmentPayment::STATUS_PAID, AppointmentPayment::STATUS_PARTIAL], true)) {
            $payload['collect_payment'] = true;
            $payload['payment_method'] = strtolower($header['payment_method']);
            $payload['amount_paid'] = $amountPaid;
        }

        if ($grandTotal < 0) {
            $payload['discount'] = $discount;
        }

        return $payload;
    }

    /**
     * @param  array<string, string>  $header
     * @return array<string, mixed>
     */
    private function paymentSnapshot(array $header, string $timezone, string $paymentStatus, float $amountPaid): array
    {
        $paidAt = null;
        if ($header['paid_at'] !== '') {
            $paidAt = $this->parseDateTime($header['paid_at'], $timezone);
        } elseif ($paymentStatus === AppointmentPayment::STATUS_PAID) {
            $paidAt = now();
        }

        return [
            'payment_status' => $paymentStatus,
            'payment_method' => $header['payment_method'] !== '' ? strtolower($header['payment_method']) : null,
            'amount_paid' => $amountPaid,
            'paid_at' => $paidAt,
        ];
    }

    /**
     * @param  array<string, string>  $customer
     */
    private function resolveCustomer(Saloon $saloon, array $customer, User $actor): ?int
    {
        $name = trim($customer['name']);
        $phone = PhoneNumber::normalize($customer['phone']);

        if ($name === '' && ($phone === null || $phone === '')) {
            return null;
        }

        $existing = null;
        if ($phone !== null && $phone !== '') {
            $existing = Customer::query()
                ->forSaloon((int) $saloon->id)
                ->where('phone', $phone)
                ->first();
        }

        $attributes = array_filter([
            'name' => $name !== '' ? $name : null,
            'email' => trim($customer['email']) !== '' ? trim($customer['email']) : null,
            'whatsapp' => PhoneNumber::normalize($customer['whatsapp']),
            'birthday' => $customer['birthday'] !== '' ? $customer['birthday'] : null,
            'anniversary' => $customer['anniversary'] !== '' ? $customer['anniversary'] : null,
            'notes' => trim($customer['notes']) !== '' ? trim($customer['notes']) : null,
        ], fn ($value) => $value !== null);

        if ($existing !== null) {
            $fill = $attributes;
            if ($existing->email && array_key_exists('email', $fill) && $fill['email'] === null) {
                unset($fill['email']);
            }
            $existing->fill($fill);
            if ($phone !== null && $existing->phone !== $phone) {
                $existing->phone = $phone;
            }
            $existing->save();

            return (int) $existing->id;
        }

        $created = Customer::query()->create([
            'name' => $name,
            'phone' => $phone,
            'email' => $attributes['email'] ?? null,
            'whatsapp' => $attributes['whatsapp'] ?? null,
            'birthday' => $attributes['birthday'] ?? null,
            'anniversary' => $attributes['anniversary'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'is_active' => true,
            'created_by' => $actor->id,
        ]);
        $created->attachSaloon((int) $saloon->id);

        return (int) $created->id;
    }

    /**
     * @param  array{appointment_code: string, rows: list<array{row: int, data: array<string, string>}>}  $group
     * @return list<array{row: int, appointment_code: string, message: string}>
     */
    private function validateGroup(Saloon $saloon, array $group): array
    {
        $errors = [];
        $header = $group['rows'][0]['data'];
        $code = $group['appointment_code'];
        $timezone = (string) $this->tenantConfig->get($saloon, 'regional', 'timezone', 'Asia/Kolkata');

        foreach (array_slice($group['rows'], 1) as $row) {
            foreach (self::VISIT_FIELDS as $field) {
                $next = $row['data'][$field];
                $first = $header[$field];
                if ($next !== '' && $first !== '' && $next !== $first) {
                    $errors[] = $this->error($row['row'], $code, "{$field} does not match the other rows for {$code}.");
                }
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        if (strlen($code) > 120) {
            return [$this->error($group['rows'][0]['row'], $code, 'appointment_code must be 120 characters or fewer.')];
        }

        $alreadyImported = Appointment::query()
            ->where('saloon_id', $saloon->id)
            ->where('import_key', $code)
            ->exists();

        if ($alreadyImported) {
            return [$this->error($group['rows'][0]['row'], $code, "Appointment {$code} was already imported.")];
        }

        $branch = $this->findBranch($saloon, $header['branch_name']);
        if ($branch === null) {
            return [$this->error($group['rows'][0]['row'], $code, "Branch \"{$header['branch_name']}\" was not found. Add it in the app before importing.")];
        }

        $status = strtolower($header['status']);
        if (! in_array($status, [
            AppointmentStatus::SCHEDULED,
            AppointmentStatus::CONFIRMED,
            AppointmentStatus::COMPLETED,
            AppointmentStatus::CANCELLED,
            AppointmentStatus::NO_SHOW,
        ], true)) {
            return [$this->error($group['rows'][0]['row'], $code, 'status must be scheduled, confirmed, completed, cancelled, or no-show.')];
        }

        $type = $header['type'] !== '' ? strtolower($header['type']) : Appointment::TYPE_APPOINTMENT;
        if (! in_array($type, [Appointment::TYPE_APPOINTMENT, Appointment::TYPE_WALK_IN], true)) {
            return [$this->error($group['rows'][0]['row'], $code, 'type must be appointment or walk_in.')];
        }

        $customerName = trim($header['customer_name']);
        $customerPhone = trim($header['customer_phone']);
        if ($type === Appointment::TYPE_APPOINTMENT && $customerName === '' && $customerPhone === '') {
            return [$this->error($group['rows'][0]['row'], $code, 'customer_name is required unless the visit is a walk-in.')];
        }

        if ($customerPhone !== '' && ! PhoneNumber::isValid(PhoneNumber::normalize($customerPhone))) {
            return [$this->error($group['rows'][0]['row'], $code, 'customer_phone must include the country code, for example +919811110001.')];
        }

        if ($customerName === '' && $customerPhone !== '') {
            return [$this->error($group['rows'][0]['row'], $code, 'customer_name is required when customer_phone is filled.')];
        }

        foreach (['customer_birthday', 'customer_anniversary'] as $dateField) {
            if ($header[$dateField] !== '' && ! $this->isDate($header[$dateField])) {
                return [$this->error($group['rows'][0]['row'], $code, "{$dateField} must be YYYY-MM-DD.")];
            }
        }

        if ($header['customer_email'] !== '' && ! filter_var($header['customer_email'], FILTER_VALIDATE_EMAIL)) {
            return [$this->error($group['rows'][0]['row'], $code, 'customer_email is not a valid email address.')];
        }

        if ($header['customer_whatsapp'] !== '' && ! PhoneNumber::isValid(PhoneNumber::normalize($header['customer_whatsapp']))) {
            return [$this->error($group['rows'][0]['row'], $code, 'customer_whatsapp must include the country code, for example +919811110001.')];
        }

        $discount = $header['discount'] !== '' ? $header['discount'] : '0';
        if (! is_numeric($discount) || (float) $discount < 0) {
            return [$this->error($group['rows'][0]['row'], $code, 'discount must be a number zero or greater.')];
        }

        if ($header['grand_total'] === '' || ! is_numeric($header['grand_total']) || (float) $header['grand_total'] < 0) {
            return [$this->error($group['rows'][0]['row'], $code, 'grand_total is required and must be a number zero or greater.')];
        }

        $paymentError = $this->validatePayment($group['rows'][0]['row'], $code, $header);
        if ($paymentError !== null) {
            return [$paymentError];
        }

        $lineTotal = 0.0;
        $lineErrors = [];
        foreach ($group['rows'] as $row) {
            $rowErrors = $this->validateLine($saloon, (int) $branch->id, $code, $row, $timezone);
            array_push($lineErrors, ...$rowErrors);
            if ($rowErrors === [] && is_numeric($row['data']['line_price'])) {
                $lineTotal += (float) $row['data']['line_price'];
            }
        }

        if ($lineErrors !== []) {
            return $lineErrors;
        }

        $expected = round($lineTotal - (float) $discount, 2);
        $stated = round((float) $header['grand_total'], 2);
        if (abs($expected - $stated) > 0.02) {
            return [$this->error(
                $group['rows'][0]['row'],
                $code,
                "grand_total {$stated} does not match the service prices minus discount ({$expected}).",
            )];
        }

        $paymentStatus = strtolower($header['payment_status']);
        $amountPaid = round((float) ($header['amount_paid'] !== '' ? $header['amount_paid'] : 0), 2);
        $derived = AppointmentPayment::resolveStatus($amountPaid, $stated);
        if ($paymentStatus !== AppointmentPayment::STATUS_REFUNDED && $paymentStatus !== $derived) {
            return [$this->error(
                $group['rows'][0]['row'],
                $code,
                "payment_status {$paymentStatus} does not match amount_paid {$amountPaid} against grand_total {$stated}.",
            )];
        }

        return [];
    }

    /**
     * @param  array{row: int, data: array<string, string>}  $row
     * @return list<array{row: int, appointment_code: string, message: string}>
     */
    private function validateLine(Saloon $saloon, int $branchId, string $code, array $row, string $timezone): array
    {
        $data = $row['data'];
        $line = (int) $row['row'];

        if ($data['service_name'] === '') {
            return [$this->error($line, $code, 'service_name is required.')];
        }

        if ($data['staff_phone'] === '') {
            return [$this->error($line, $code, 'staff_phone is required.')];
        }

        $staffPhone = PhoneNumber::normalize($data['staff_phone']);
        if (! PhoneNumber::isValid($staffPhone)) {
            return [$this->error($line, $code, 'staff_phone must include the country code, for example +919800000001.')];
        }

        $staff = $this->findStaff($saloon, $data['staff_phone']);
        if ($staff === null) {
            return [$this->error($line, $code, "Staff phone {$data['staff_phone']} was not found in this salon. Add the staff member before importing.")];
        }

        if ($staff->branch_id !== null && (int) $staff->branch_id !== $branchId) {
            return [$this->error($line, $code, "{$staff->name} is not assigned to this branch.")];
        }

        $offering = $this->findOffering($saloon, $branchId, $data['service_name']);
        if ($offering === null) {
            return [$this->error($line, $code, "Service \"{$data['service_name']}\" is not on this branch menu. Add the offering before importing.")];
        }

        if ($data['line_price'] === '' || ! is_numeric($data['line_price']) || (float) $data['line_price'] < 0) {
            return [$this->error($line, $code, 'line_price is required and must be a number zero or greater.')];
        }

        if ($data['duration_minutes'] === '' || ! is_numeric($data['duration_minutes']) || (int) $data['duration_minutes'] < 1) {
            return [$this->error($line, $code, 'duration_minutes must be a whole number of at least 1.')];
        }

        if ($data['starts_at'] === '' || $data['ends_at'] === '') {
            return [$this->error($line, $code, 'starts_at and ends_at are required.')];
        }

        try {
            $startsAt = $this->parseDateTime($data['starts_at'], $timezone);
            $endsAt = $this->parseDateTime($data['ends_at'], $timezone);
        } catch (Throwable) {
            return [$this->error($line, $code, 'starts_at and ends_at must be YYYY-MM-DD HH:MM.')];
        }

        if ($endsAt->lte($startsAt)) {
            return [$this->error($line, $code, 'ends_at must be after starts_at.')];
        }

        $minutes = (int) abs($startsAt->diffInMinutes($endsAt));
        if (abs($minutes - (int) $data['duration_minutes']) > 1) {
            return [$this->error($line, $code, 'duration_minutes does not match starts_at and ends_at.')];
        }

        return [];
    }

    /**
     * @param  array<string, string>  $header
     * @return array{row: int, appointment_code: string, message: string}|null
     */
    private function validatePayment(int $row, string $code, array $header): ?array
    {
        $status = strtolower($header['payment_status']);
        if (! in_array($status, AppointmentPayment::STATUSES, true)) {
            return $this->error($row, $code, 'payment_status must be unpaid, partial, paid, or refunded.');
        }

        $amount = $header['amount_paid'] !== '' ? $header['amount_paid'] : '0';
        if (! is_numeric($amount) || (float) $amount < 0) {
            return $this->error($row, $code, 'amount_paid must be a number zero or greater.');
        }

        $method = strtolower($header['payment_method']);
        $needsMethod = in_array($status, [AppointmentPayment::STATUS_PAID, AppointmentPayment::STATUS_PARTIAL], true);
        if ($needsMethod && ! in_array($method, AppointmentPayment::METHODS, true)) {
            return $this->error($row, $code, 'payment_method must be cash, card, upi, bank_transfer, or other when the visit is paid or partial.');
        }

        if ($method !== '' && ! in_array($method, AppointmentPayment::METHODS, true)) {
            return $this->error($row, $code, 'payment_method must be cash, card, upi, bank_transfer, or other.');
        }

        if ($header['paid_at'] !== '') {
            try {
                Carbon::parse($header['paid_at']);
            } catch (Throwable) {
                return $this->error($row, $code, 'paid_at must be YYYY-MM-DD HH:MM.');
            }
        }

        if (strlen($header['invoice_number']) > 100) {
            return $this->error($row, $code, 'invoice_number must be 100 characters or fewer.');
        }

        if (strlen($header['notes']) > 2000 || strlen($header['customer_notes']) > 2000) {
            return $this->error($row, $code, 'notes must be 2000 characters or fewer.');
        }

        return null;
    }

    private function findBranch(Saloon $saloon, string $name): ?SaloonBranch
    {
        $wanted = mb_strtolower(trim($name));
        if ($wanted === '') {
            return null;
        }

        return SaloonBranch::query()
            ->where('saloon_id', $saloon->id)
            ->get()
            ->first(fn (SaloonBranch $branch): bool => mb_strtolower(trim($branch->branch_name)) === $wanted);
    }

    private function findStaff(Saloon $saloon, string $phone): ?User
    {
        $normalized = PhoneNumber::normalize($phone);
        if ($normalized === null) {
            return null;
        }

        return User::query()
            ->where('saloon_id', $saloon->id)
            ->where('is_system_admin', false)
            ->where('phone', $normalized)
            ->first();
    }

    private function findOffering(Saloon $saloon, int $branchId, string $serviceName): ?SalonServiceProduct
    {
        $wanted = mb_strtolower(trim($serviceName));

        $matches = SalonServiceProduct::query()
            ->with('service')
            ->where('saloon_id', $saloon->id)
            ->where('is_active', true)
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->get()
            ->filter(fn (SalonServiceProduct $row): bool => mb_strtolower(trim((string) $row->service?->name)) === $wanted);

        return $matches->firstWhere('product_id', null) ?? $matches->first();
    }

    private function parseDateTime(string $value, string $timezone): Carbon
    {
        return Carbon::parse(trim($value), $timezone);
    }

    private function isDate(string $value): bool
    {
        $parsed = date_create_from_format('Y-m-d', trim($value));

        return $parsed !== false && $parsed->format('Y-m-d') === trim($value);
    }

    /**
     * @return array{rows: list<array{row: int, data: array<string, string>}>, errors: list<array{row: int, appointment_code: string, message: string}>}
     */
    private function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return [
                'rows' => [],
                'errors' => [$this->error(1, '', 'The file is empty. Download the sample CSV and fill it in.')],
            ];
        }

        $columns = [];
        foreach ($header as $index => $name) {
            $key = strtolower(trim((string) $name));
            if ($key !== '') {
                $columns[$key] = $index;
            }
        }

        $missing = array_values(array_filter(
            self::HEADERS,
            fn (string $name): bool => ! array_key_exists($name, $columns),
        ));

        if ($missing !== []) {
            fclose($handle);

            return [
                'rows' => [],
                'errors' => [$this->error(1, '', 'Missing columns: '.implode(', ', $missing).'.')],
            ];
        }

        $rows = [];
        $errors = [];
        $line = 1;

        while (($csv = fgetcsv($handle)) !== false) {
            $line++;
            if ($this->rowIsEmpty($csv)) {
                continue;
            }

            $data = [];
            foreach (self::HEADERS as $name) {
                $index = $columns[$name];
                $data[$name] = trim((string) ($csv[$index] ?? ''));
            }

            if ($data['appointment_code'] === '') {
                $errors[] = $this->error($line, '', 'appointment_code is required.');

                continue;
            }

            $rows[] = ['row' => $line, 'data' => $data];
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  list<array{row: int, data: array<string, string>}>  $rows
     * @return array{groups: list<array{appointment_code: string, rows: list<array{row: int, data: array<string, string>}>}>, invalid: list<array{row: int, appointment_code: string, message: string}>, invalid_codes: list<string>}
     */
    private function groupRows(array $rows): array
    {
        $buckets = [];
        foreach ($rows as $row) {
            $buckets[$row['data']['appointment_code']][] = $row;
        }

        $groups = [];
        foreach ($buckets as $code => $bucket) {
            $groups[] = [
                'appointment_code' => (string) $code,
                'rows' => $bucket,
            ];
        }

        return [
            'groups' => $groups,
            'invalid' => [],
            'invalid_codes' => [],
        ];
    }

    /**
     * @param  list<string|null>  $csv
     */
    private function rowIsEmpty(array $csv): bool
    {
        foreach ($csv as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{row: int, appointment_code: string, message: string}
     */
    private function error(int $row, string $code, string $message): array
    {
        return [
            'row' => $row,
            'appointment_code' => $code,
            'message' => $message,
        ];
    }

    private function exceptionMessage(ValidationException $exception): string
    {
        $messages = collect($exception->errors())->flatten()->unique()->filter()->values();

        return $messages->isEmpty() ? 'This visit could not be imported.' : (string) $messages->implode(' ');
    }
}
