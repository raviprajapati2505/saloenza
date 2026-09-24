<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $salonName }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b;">
    <p>Hi {{ $customerName }},</p>

    <div style="white-space: pre-wrap;">{{ $bodyText }}</div>

    <p style="margin-top: 24px;">— {{ $salonName }}</p>

    @include('emails.partials.tenant-footer')
</body>
</html>
