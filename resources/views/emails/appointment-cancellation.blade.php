<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Cancelled</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b;">
    <p>Hi {{ $customerName }},</p>

    <p>
        Your appointment at <strong>{{ $salonName }}</strong> scheduled for
        <strong>{{ $startsAt }}</strong> has been cancelled.
    </p>

    <p>Contact us if you would like to reschedule.</p>

    @include('emails.partials.tenant-footer')
</body>
</html>
