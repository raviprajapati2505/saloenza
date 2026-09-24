<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>We miss you</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b;">
    <p>Hi {{ $customerName }},</p>

    <p>
        It has been a while since we saw you at <strong>{{ $salonName }}</strong>.
        We would love to welcome you back for your next visit.
    </p>

    @if ($note !== '')
        <p style="padding: 14px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
            {{ $note }}
        </p>
    @endif

    <p>Reply to this email or book online whenever you are ready.</p>

    @include('emails.partials.tenant-footer')
</body>
</html>
