<?php

namespace App\Services\Billing;

use App\Models\Appointment;
use App\Services\Tenant\TenantSettingsService;
use App\Support\Tenant\TenantConfig;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoiceReceiptService
{
    public function __construct(
        private readonly TenantConfig $tenantConfig,
        private readonly TenantSettingsService $tenantSettingsService,
    ) {}

    public function ensureInvoiceNumber(Appointment $appointment): string
    {
        if (filled($appointment->invoice_number)) {
            return (string) $appointment->invoice_number;
        }

        $appointment->loadMissing('saloon');
        $salon = $appointment->saloon;
        $invoiceSettings = $salon
            ? $this->tenantConfig->group($salon, 'invoice')
            : ['invoice_prefix' => 'INV-'];

        $prefix = (string) ($invoiceSettings['invoice_prefix'] ?? 'INV-');
        $number = $prefix.str_pad((string) $appointment->id, 6, '0', STR_PAD_LEFT);

        $appointment->forceFill(['invoice_number' => $number])->save();

        return $number;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildReceiptData(Appointment $appointment): array
    {
        $appointment->loadMissing([
            'saloon',
            'branch',
            'customer',
            'staff',
            'service',
            'services.service',
            'services.product',
            'services.staff',
            'products.product',
            'products.staff',
        ]);

        $salon = $appointment->saloon;
        $invoiceSettings = $salon
            ? $this->tenantConfig->group($salon, 'invoice')
            : [];
        $branding = $salon
            ? $this->tenantSettingsService->brandingPayload($salon)
            : [];
        $regional = $salon
            ? $this->tenantConfig->group($salon, 'regional')
            : ['currency' => 'QAR'];

        $currency = (string) ($regional['currency'] ?? 'QAR');
        $allowed = ['QAR', 'USD'];
        if (! in_array($currency, $allowed, true)) {
            $currency = 'QAR';
        }
        $currencySymbol = match ($currency) {
            'USD' => '$',
            default => 'ر.ق',
        };

        $serviceLines = $appointment->services->map(fn ($line): array => [
            'name' => trim(($line->service?->name ?? 'Service').($line->product?->name ? ' · '.$line->product->name : '')),
            'staff' => $line->staff?->name,
            'quantity' => max(1, (int) ($line->quantity ?? 1)),
            'price' => (float) $line->price * max(1, (int) ($line->quantity ?? 1)),
        ])->all();

        $productLines = $appointment->products->map(fn ($line): array => [
            'name' => ($line->product?->name ?? 'Product').' (retail)',
            'staff' => $line->staff?->name,
            'quantity' => max(1, (int) $line->quantity),
            'price' => (float) $line->line_total,
        ])->all();

        $lines = [...$serviceLines, ...$productLines];

        if ($lines === []) {
            $lines = [[
                'name' => $appointment->service?->name ?? 'Service',
                'staff' => $appointment->staff?->name,
                'quantity' => 1,
                'price' => (float) $appointment->price,
            ]];
        }

        return [
            'invoiceNumber' => $this->ensureInvoiceNumber($appointment),
            'issuedAt' => now()->format('d M Y, h:i A'),
            'salonName' => $salon?->name ?? 'Salon',
            'salonAddress' => trim(implode(', ', array_filter([
                $salon?->address,
                $salon?->city,
                $salon?->state,
            ]))),
            'salonPhone' => $salon?->phone,
            'gstNumber' => ((bool) ($invoiceSettings['show_gst_number'] ?? true)) ? $salon?->gst_number : null,
            'taxLabel' => (string) ($invoiceSettings['tax_label'] ?? 'GST'),
            'footerNote' => (string) ($invoiceSettings['footer_note'] ?? ''),
            'branding' => $branding,
            'customerName' => $appointment->customer?->name ?? 'Walk-in',
            'customerPhone' => $appointment->customer?->phone,
            'branchName' => $appointment->branch?->branch_name,
            'appointmentAt' => $appointment->starts_at?->format('d M Y, h:i A') ?? '—',
            'status' => ucfirst(str_replace('-', ' ', (string) $appointment->status)),
            'lines' => $lines,
            'subtotal' => (float) $appointment->price,
            'discount' => (float) $appointment->discount,
            'grandTotal' => (float) ($appointment->grand_total ?? $appointment->price),
            'paymentStatus' => ucfirst(str_replace('-', ' ', (string) ($appointment->payment_status ?? 'unpaid'))),
            'paymentMethod' => $appointment->payment_method
                ? ucfirst(str_replace('_', ' ', (string) $appointment->payment_method))
                : null,
            'amountPaid' => (float) ($appointment->amount_paid ?? 0),
            'currencySymbol' => $currencySymbol,
        ];
    }

    public function pdfResponse(Appointment $appointment): Response
    {
        $data = $this->buildReceiptData($appointment);
        $filename = 'receipt-'.$data['invoiceNumber'].'.pdf';

        return Pdf::loadView('pdf.appointment-receipt', $data)
            ->setPaper('a4')
            ->download($filename);
    }
}
