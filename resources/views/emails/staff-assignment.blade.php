<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Assigned</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b;">
    <p>Hi {{ $staffName }},</p>

    <p>
        You have been assigned a new appointment at <strong>{{ $salonName }}</strong>
        on <strong>{{ $startsAt }}</strong>.
    </p>

    <p>Customer: <strong>{{ $customerName }}</strong></p>

    @include('emails.partials.tenant-footer')
</body>
</html>
