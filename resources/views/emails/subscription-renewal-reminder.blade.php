<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subscription Renewal Reminder</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b;">
    <p>Hi {{ $userName }},</p>

    <p>
        Your subscription for <strong>{{ $salonName }}</strong> on the
        <strong>{{ $planName }}</strong> plan renews in
        <strong>{{ $daysRemaining }} days</strong> on {{ $dueDate }}.
    </p>

    <p>
        Please renew your subscription to avoid interruption of salon features.
    </p>

    <p>
        <a href="{{ $billingUrl }}" style="color: #2563eb;">Renew subscription</a>
    </p>

    <p>If you have already renewed, you can ignore this email.</p>

    @include('emails.partials.tenant-footer')
</body>
</html>
