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
<body style="margin:0;padding:0;background:#151d28;font-family:Arial,Helvetica,sans-serif;">
<span style="display:none !important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">
    Tvoj jednorazový kód na obnovu hesla.
</span>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;background:#151d28;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">

                {{-- Logo header --}}
                <tr>
                    <td style="padding:28px 32px;background:#0F73FF;border-radius:20px 20px 0 0;text-align:center;">
                        <img src="{{ $logoUrl }}" width="140" alt="{{ $appName }}" style="display:block;margin:0 auto;max-width:140px;height:auto;border:0;outline:none;text-decoration:none;">
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:32px;background:#1c2736;border-radius:0 0 20px 20px;">

                        <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;font-weight:700;color:#ffffff;">
                            {{ $title }}
                        </h1>
                        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#ABB8C9;">
                            {{ $intro }}
                        </p>

                        {{-- Code block --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 8px;">
                            <tr>
                                <td style="padding:20px 16px;background:#151d28;border-radius:14px;text-align:center;font-size:36px;line-height:1.1;letter-spacing:4px;font-weight:700;color:#ffffff;">
                                    {{ $code }}
                                </td>
                            </tr>
                        </table>
                        <p style="margin:0 0 24px;font-size:13px;line-height:1.5;color:#ABB8C9;">
                            Kód platí {{ $ttlMinutes }} minút.
                        </p>

                        <p style="margin:0 0 8px;font-size:14px;line-height:1.6;font-weight:600;color:#ffffff;">
                            Ak si o obnovu hesla nevyžiadal/a ty, tento e-mail môžeš bezpečne ignorovať.
                        </p>
                        <p style="margin:0;font-size:13px;line-height:1.6;color:#ABB8C9;">
                            Z bezpečnostných dôvodov má tento kód obmedzenú platnosť.
                        </p>

                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:20px 16px 0;font-size:12px;line-height:1.6;color:#ABB8C9;text-align:center;opacity:0.6;">
                        © {{ date('Y') }} {{ $appName }}
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
