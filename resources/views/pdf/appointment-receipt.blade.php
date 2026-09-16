<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $invoiceNumber }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 12px; line-height: 1.5; }
        .header { border-bottom: 2px solid {{ $branding['primary_color'] ?? '#cc0f67' }}; padding-bottom: 12px; margin-bottom: 18px; }
        .brand { font-size: 20px; font-weight: bold; color: {{ $branding['primary_color'] ?? '#cc0f67' }}; }
        .muted { color: #64748b; }
        .grid { width: 100%; margin-bottom: 16px; }
        .grid td { vertical-align: top; width: 50%; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.items th, table.items td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        table.items th { background: #f8fafc; }
        .totals { width: 100%; margin-top: 16px; }
        .totals td { padding: 4px 0; }
        .totals .label { text-align: right; padding-right: 12px; color: #64748b; }
        .grand { font-size: 16px; font-weight: bold; color: {{ $branding['primary_color'] ?? '#cc0f67' }}; }
        .footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ $branding['portal_name'] ?? $salonName }}</div>
        <div class="muted">{{ $salonName }}</div>
        @if(!empty($salonAddress))<div class="muted">{{ $salonAddress }}</div>@endif
        @if(!empty($salonPhone))<div class="muted">{{ $salonPhone }}</div>@endif
        @if(!empty($gstNumber))<div class="muted">{{ $taxLabel }}: {{ $gstNumber }}</div>@endif
    </div>

    <table class="grid">
        <tr>
            <td>
                <strong>Bill To</strong><br>
                {{ $customerName }}<br>
                @if(!empty($customerPhone))<span class="muted">{{ $customerPhone }}</span>@endif
            </td>
            <td style="text-align: right;">
                <strong>Receipt #</strong> {{ $invoiceNumber }}<br>
                <span class="muted">Issued: {{ $issuedAt }}</span><br>
                <span class="muted">Appointment: {{ $appointmentAt }}</span><br>
                @if(!empty($branchName))<span class="muted">Branch: {{ $branchName }}</span><br>@endif
                <span class="muted">Status: {{ $status }}</span>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th>Staff</th>
                <th style="text-align: center; width: 48px;">Qty</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
                <tr>
                    <td>{{ $line['name'] }}</td>
                    <td>{{ $line['staff'] ?? '—' }}</td>
                    <td style="text-align: center;">{{ $line['quantity'] ?? 1 }}</td>
                    <td style="text-align: right;">{{ $currencySymbol }}{{ number_format($line['price'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Subtotal</td>
            <td style="width: 120px; text-align: right;">{{ $currencySymbol }}{{ number_format($subtotal, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Discount</td>
            <td style="text-align: right;">-{{ $currencySymbol }}{{ number_format($discount, 2) }}</td>
        </tr>
        <tr>
            <td class="label grand">Grand Total</td>
            <td class="grand" style="text-align: right;">{{ $currencySymbol }}{{ number_format($grandTotal, 2) }}</td>
        </tr>
        @if(($amountPaid ?? 0) > 0)
        <tr>
            <td class="label">Paid ({{ $paymentMethod ?? '—' }})</td>
            <td style="text-align: right;">{{ $currencySymbol }}{{ number_format($amountPaid, 2) }}</td>
        </tr>
        <tr>
            <td class="label">Payment Status</td>
            <td style="text-align: right;">{{ $paymentStatus ?? 'Unpaid' }}</td>
        </tr>
        @endif
    </table>

    @if(!empty($footerNote))
        <div class="footer">{{ $footerNote }}</div>
    @else
        <div class="footer">Thank you for your visit.</div>
    @endif
</body>
</html>
