<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b;">
    <p>Hi {{ $customerName }},</p>

    <p>
        This is a friendly reminder about your upcoming appointment at
        <strong>{{ $salonName }}</strong> on <strong>{{ $startsAt }}</strong>.
    </p>

    <p>Service: <strong>{{ $serviceName }}</strong></p>

    <p>We look forward to seeing you. Reply or call us if you need to reschedule.</p>

    @include('emails.partials.tenant-footer')
</body>
</html>
