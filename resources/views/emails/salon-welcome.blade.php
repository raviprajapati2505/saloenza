<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to {{ $portalName }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e293b;">
    <p>Hi {{ $userName }},</p>

    <p>
        Your salon <strong>{{ $salonName }}</strong> has been set up on {{ $portalName }}.
        You can sign in with your email address and start managing your salon.
    </p>

    <p>
        <strong>Login email:</strong> {{ $email }}<br>
        <a href="{{ $loginUrl }}" style="color: #2563eb;">Sign in to {{ $portalName }}</a>
    </p>

    @if(!empty($temporaryPassword))
        <p>
            <strong>Temporary password:</strong> {{ $temporaryPassword }}
        </p>
    @endif

    <p>If you did not expect this email, please contact support.</p>

    @include('emails.partials.tenant-footer')
</body>
</html>
