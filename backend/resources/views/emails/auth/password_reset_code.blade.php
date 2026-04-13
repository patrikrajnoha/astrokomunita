@php
    $title = 'Obnova hesla';
    $intro = 'Použi tento jednorazový kód vo formulári na obnovu hesla.';
    $appName = (string) config('app.name', 'Astrokomunita');
    $frontendUrl = rtrim((string) env('FRONTEND_URL', config('app.url', 'http://localhost')), '/');
    $logoUrl = $frontendUrl . '/logo.png';
    $ttlMinutes = max(5, (int) config('password_reset.code_ttl_minutes', 20));
@endphp
<!doctype html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#0b1220;color:#e5e7eb;font-family:Arial,sans-serif;">
<span style="display:none !important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">
    Tvoj jednorazový kód na obnovu hesla.
</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;background:#0b1220;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#111827;border:1px solid #1f2937;border-radius:16px;overflow:hidden;">
                <tr>
                    <td style="padding:22px 24px;background:linear-gradient(135deg,#1d4ed8,#0f766e);text-align:center;">
                        <img src="{{ $logoUrl }}" width="148" alt="{{ $appName }}" style="display:block;margin:0 auto;max-width:148px;height:auto;border:0;outline:none;text-decoration:none;">
                    </td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        <h1 style="margin:0 0 12px;font-size:24px;line-height:1.3;color:#f8fafc;">{{ $title }}</h1>
                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#e2e8f0;">
                            {{ $intro }}
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
                            <tr>
                                <td style="padding:16px;border:1px solid #334155;border-radius:12px;background:#0f172a;text-align:center;font-size:34px;line-height:1.1;letter-spacing:2px;font-weight:700;color:#f8fafc;">
                                    {{ $code }}
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0 0 16px;font-size:13px;line-height:1.5;color:#94a3b8;">
                            Kód platí {{ $ttlMinutes }} minút.
                        </p>

                        <p style="margin:0 0 8px;font-size:14px;line-height:1.6;color:#e2e8f0;">
                            Ak si o obnovu hesla nevyžiadal/a ty, tento e-mail môžeš bezpečne ignorovať.
                        </p>
                        <p style="margin:0;font-size:13px;line-height:1.6;color:#94a3b8;">
                            Z bezpečnostných dôvodov má tento kód obmedzenú platnosť.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 24px 24px;font-size:12px;line-height:1.6;color:#64748b;text-align:center;">
                        © {{ date('Y') }} {{ $appName }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
