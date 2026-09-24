<?php

namespace Tests\Feature\Appointment;

use App\Models\Appointment;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerValueStat;
use App\Models\Role;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\User;
use App\Services\Appointment\AppointmentImportService;
use App\Support\Role\RoleCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppointmentImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_csv_downloads_for_the_salon_owner(): void
    {
        $this->actingAsOwner(['phone' => '+917000001001']);

        $this->get('/api/v1/appointment-imports/sample.csv')
            ->assertOk()
            ->assertHeader('content-disposition')
            ->assertSee('appointment_code', false)
            ->assertSee('APT-1001', false);
    }

    public function test_staff_and_managers_cannot_import_appointments(): void
    {
        $owner = $this->createOwnerUser(['phone' => '+917000001002']);
        $salon = Saloon::query()->findOrFail($owner->saloon_id);
        $staff = $this->staffOn($salon, '+919800000099');
        $managerRole = Role::findByCode(RoleCodes::SALON_FRANCHISE_MANAGER);
        $manager = User::factory()->create([
            'saloon_id' => $salon->id,
            'role_id' => $managerRole?->id,
            'phone' => '+917000001003',
            'is_active' => true,
        ]);

        Sanctum::actingAs($staff);
        $this->post('/api/v1/appointment-imports/preview', [
            'file' => $this->csvFile([$this->visitRow()]),
        ])->assertForbidden();

        Sanctum::actingAs($manager);
        $this->post('/api/v1/appointment-imports/preview', [
            'file' => $this->csvFile([$this->visitRow()]),
        ])->assertForbidden();
    }

    public function test_owner_imports_a_visit_that_other_modules_can_read(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+917000001004']);
        $salon = Saloon::query()->findOrFail($owner->saloon_id);
        $this->seedMenu($salon, '+919800000010');

        $preview = $this->post('/api/v1/appointment-imports/preview', [
            'file' => $this->csvFile([
                $this->visitRow([
                    'appointment_code' => 'APT-2001',
                    'service_name' => 'Import Haircut',
                    'line_price' => '800',
                    'grand_total' => '1500',
                    'amount_paid' => '1500',
                    'starts_at' => '2026-03-01 10:00',
                    'ends_at' => '2026-03-01 10:45',
                ]),
                $this->visitRow([
                    'appointment_code' => 'APT-2001',
                    'service_name' => 'Import Facial',
                    'line_price' => '700',
                    'grand_total' => '1500',
                    'amount_paid' => '1500',
                    'starts_at' => '2026-03-01 10:45',
                    'ends_at' => '2026-03-01 11:30',
                ]),
            ]),
        ])->assertOk()
            ->assertJsonPath('data.valid_count', 1)
            ->assertJsonPath('data.invalid_count', 0);

        $token = $preview->json('data.token');

        $this->postJson('/api/v1/appointment-imports/commit', [
            'token' => $token,
            'limit' => 20,
        ])->assertOk()
            ->assertJsonPath('data.done', true)
            ->assertJsonPath('data.results.0.inserted', true);

        $appointment = Appointment::query()->where('import_key', 'APT-2001')->first();
        $this->assertNotNull($appointment);
        $this->assertSame('completed', $appointment->status);
        $this->assertSame('paid', $appointment->payment_status);
        $this->assertEquals(1500, (float) $appointment->grand_total);
        $this->assertSame('import', $appointment->booking_source);
        $this->assertCount(2, $appointment->services);

        $customer = Customer::query()->where('phone', '+919811110010')->first();
        $this->assertNotNull($customer);
        $this->assertTrue($customer->belongsToSaloon($salon->id));
        $this->assertSame('Asha Patel', $customer->name);

        $this->getJson('/api/v1/appointments?search=Asha')
            ->assertOk()
            ->assertJsonPath('data.appointments.0.customer.name', 'Asha Patel');

        $stat = CustomerValueStat::query()
            ->where('saloon_id', $salon->id)
            ->where('customer_id', $customer->id)
            ->first();
        $this->assertNotNull($stat);
        $this->assertSame(1, (int) $stat->visit_count);

        $this->post('/api/v1/appointment-imports/preview', [
            'file' => $this->csvFile([
                $this->visitRow(['appointment_code' => 'APT-2001']),
            ]),
        ])->assertOk()
            ->assertJsonPath('data.valid_count', 0)
            ->assertJsonPath('data.errors.0.message', 'Appointment APT-2001 was already imported.');
    }

    public function test_unknown_service_is_reported_and_nothing_is_inserted(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+917000001005']);
        $salon = Saloon::query()->findOrFail($owner->saloon_id);
        $this->seedMenu($salon, '+919800000011');

        $this->post('/api/v1/appointment-imports/preview', [
            'file' => $this->csvFile([
                $this->visitRow([
                    'appointment_code' => 'APT-BAD',
                    'service_name' => 'Not On The Menu',
                    'line_price' => '800',
                    'grand_total' => '800',
                    'amount_paid' => '800',
                ]),
            ]),
        ])->assertOk()
            ->assertJsonPath('data.valid_count', 0)
            ->assertJsonPath('data.token', null)
            ->assertJsonPath('data.errors.0.row', 2);

        $this->assertDatabaseMissing('appointments', ['import_key' => 'APT-BAD']);
    }

    public function test_super_admin_must_choose_a_salon_and_can_import_into_it(): void
    {
        $owner = $this->createOwnerUser(['phone' => '+917000001006']);
        $salon = Saloon::query()->findOrFail($owner->saloon_id);
        $this->seedMenu($salon, '+919800000012');

        $this->actingAsSystemAdmin(['email' => 'import.admin@example.test', 'phone' => '+917000001007']);

        $this->post('/api/v1/appointment-imports/preview', [
            'file' => $this->csvFile([$this->visitRow([
                'appointment_code' => 'APT-ADMIN',
                'line_price' => '800',
                'grand_total' => '800',
                'amount_paid' => '800',
            ])]),
        ])->assertStatus(422);

        $preview = $this->post('/api/v1/appointment-imports/preview', [
            'saloon_id' => $salon->id,
            'file' => $this->csvFile([$this->visitRow([
                'appointment_code' => 'APT-ADMIN',
                'line_price' => '800',
                'grand_total' => '800',
                'amount_paid' => '800',
            ])]),
        ])->assertOk()
            ->assertJsonPath('data.salon.id', $salon->id);

        $this->postJson('/api/v1/appointment-imports/commit', [
            'token' => $preview->json('data.token'),
            'saloon_id' => $salon->id,
        ])->assertOk()
            ->assertJsonPath('data.results.0.inserted', true);

        $this->assertDatabaseHas('appointments', [
            'saloon_id' => $salon->id,
            'import_key' => 'APT-ADMIN',
            'status' => 'completed',
        ]);
    }

    public function test_owner_cannot_import_into_another_salon(): void
    {
        $owner = $this->actingAsOwner(['phone' => '+917000001008']);
        $other = $this->createSaloon(['name' => 'Other Salon']);
        $this->seedMenu(Saloon::query()->findOrFail($owner->saloon_id), '+919800000013');

        $this->post('/api/v1/appointment-imports/preview', [
            'saloon_id' => $other->id,
            'file' => $this->csvFile([$this->visitRow()]),
        ])->assertForbidden();
    }

    private function seedMenu(Saloon $salon, string $staffPhone): User
    {
        $branch = SaloonBranch::query()->create([
            'saloon_id' => $salon->id,
            'branch_name' => 'Main Branch',
            'business_address_1' => '1 Road',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $staff = $this->staffOn($salon, $staffPhone, $branch);

        $category = Category::query()->firstOrCreate(['name' => 'Import Services'], ['is_active' => true]);
        foreach (['Import Haircut' => 800, 'Import Facial' => 700] as $name => $price) {
            $service = Service::query()->firstOrCreate(
                ['name' => $name],
                [
                    'category_id' => $category->id,
                    'default_price' => $price,
                    'duration_minutes' => 45,
                    'is_active' => true,
                ],
            );

            SalonServiceProduct::query()->create([
                'saloon_id' => $salon->id,
                'branch_id' => $branch->id,
                'service_id' => $service->id,
                'product_id' => null,
                'price' => $price,
                'duration_minutes' => 45,
                'is_active' => true,
            ]);
        }

        return $staff;
    }

    private function staffOn(Saloon $salon, string $phone, ?SaloonBranch $branch = null): User
    {
        $branch ??= SaloonBranch::query()->create([
            'saloon_id' => $salon->id,
            'branch_name' => 'Staff Branch',
            'business_address_1' => '2 Road',
            'city' => 'Mumbai',
            'state' => 'MH',
            'area_pincode' => '400001',
            'country' => 'India',
            'is_active' => true,
        ]);

        $role = Role::findByCode(RoleCodes::SALON_STAFF);

        return User::factory()->create([
            'saloon_id' => $salon->id,
            'branch_id' => $branch->id,
            'role_id' => $role?->id,
            'phone' => $phone,
            'firstname' => 'Rina',
            'lastname' => 'Shah',
            'name' => 'Rina Shah',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function visitRow(array $overrides = []): array
    {
        return array_merge([
            'appointment_code' => 'APT-1001',
            'branch_name' => 'Main Branch',
            'staff_phone' => '+919800000010',
            'service_name' => 'Import Haircut',
            'customer_name' => 'Asha Patel',
            'customer_phone' => '+919811110010',
            'customer_email' => 'asha@example.com',
            'customer_whatsapp' => '+919811110010',
            'customer_birthday' => '1992-04-12',
            'customer_anniversary' => '',
            'customer_notes' => 'Morning',
            'starts_at' => '2026-03-02 16:00',
            'ends_at' => '2026-03-02 16:45',
            'duration_minutes' => '45',
            'line_price' => '800',
            'status' => 'completed',
            'type' => 'appointment',
            'discount' => '0',
            'grand_total' => '800',
            'payment_status' => 'paid',
            'payment_method' => 'upi',
            'amount_paid' => '800',
            'paid_at' => '2026-03-02 16:50',
            'invoice_number' => 'INV-1',
            'notes' => 'Imported',
        ], $overrides);
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function csvFile(array $rows): UploadedFile
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, AppointmentImportService::HEADERS);
        foreach ($rows as $row) {
            $line = [];
            foreach (AppointmentImportService::HEADERS as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($handle, $line);
        }
        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return UploadedFile::fake()->createWithContent('appointments.csv', $contents);
    }
}
