<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Salon not found · Saloenza</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: "Instrument Sans", "Segoe UI", sans-serif;
            color: #0f172a;
            background:
                radial-gradient(ellipse 90% 70% at 0% 0%, rgba(204, 15, 103, 0.16), transparent 55%),
                linear-gradient(165deg, #fff7fa 0%, #ffffff 48%, #fce7ef 100%);
        }
        main {
            width: min(100%, 440px);
            padding: 32px 28px;
            border-radius: 28px;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(204, 15, 103, 0.16);
            box-shadow: 0 24px 60px -28px rgba(143, 10, 72, 0.35);
            text-align: center;
        }
        p.kicker {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #cc0f67;
        }
        h1 {
            margin: 12px 0 0;
            font-size: 28px;
            line-height: 1.2;
        }
        p.copy {
            margin: 12px 0 0;
            color: #475569;
            line-height: 1.6;
        }
        code {
            display: inline-block;
            margin-top: 16px;
            padding: 8px 12px;
            border-radius: 999px;
            background: #fff1f6;
            color: #9d174d;
            font-size: 13px;
        }
        a {
            display: inline-block;
            margin-top: 22px;
            padding: 12px 18px;
            border-radius: 14px;
            background: #cc0f67;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <main>
        <p class="kicker">404</p>
        <h1>This salon is not onboarded</h1>
        <p class="copy">
            There is no salon workspace for this address. Ask your administrator to onboard the salon, or continue on the main Saloenza app.
        </p>
        @if (!empty($host))
            <code>{{ $host }}</code>
        @endif
        <div>
            <a href="{{ $platformUrl ?? 'https://app.saloenza.com' }}">Open app.saloenza.com</a>
        </div>
    </main>
</body>
</html>
