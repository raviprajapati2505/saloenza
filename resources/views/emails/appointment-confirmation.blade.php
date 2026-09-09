<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Confirmed</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b;">
    <p>Hi {{ $customerName }},</p>

    <p>
        Your appointment at <strong>{{ $salonName }}</strong> is confirmed for
        <strong>{{ $startsAt }}</strong>.
    </p>

    <p>Service: <strong>{{ $serviceName }}</strong></p>

    <p>We look forward to seeing you.</p>

    @include('emails.partials.tenant-footer')
</body>
</html>
