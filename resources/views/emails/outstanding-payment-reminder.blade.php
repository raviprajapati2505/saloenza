<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b;">
    <p>Hi {{ $customerName }},</p>

    <p>
        This is a reminder from <strong>{{ $salonName }}</strong> that a balance of
        <strong>{{ $balanceDue }}</strong> is still pending from your visit on
        <strong>{{ $visitDate }}</strong>.
    </p>

    @if (!empty($invoiceNumber))
        <p>Invoice: <strong>{{ $invoiceNumber }}</strong></p>
    @endif

    <p>Please settle this when you next visit, or reply to this message if you have already paid.</p>

    @include('emails.partials.tenant-footer')
</body>
</html>
