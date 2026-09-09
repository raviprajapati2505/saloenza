<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $greeting }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b;">
    <p>Hi {{ $customerName }},</p>

    <p>
        {{ $greeting }} from all of us at <strong>{{ $salonName }}</strong>!
        We hope your day is as lovely as you make ours.
    </p>

    @if ($offerText !== '')
        <p style="padding: 14px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
            {{ $offerText }}
        </p>
    @endif

    <p>We would love to see you soon. Book a visit whenever you are ready.</p>

    @include('emails.partials.tenant-footer')
</body>
</html>
