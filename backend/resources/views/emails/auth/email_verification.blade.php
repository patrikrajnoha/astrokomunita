@php
    $isEmailChange = $purpose === 'email_change_current';
    $title = $isEmailChange ? 'Potvrdenie zmeny e-mailu' : 'Overenie e-mailovej adresy';
    $intro = $isEmailChange
        ? 'Použite tento jednorazový kód na potvrdenie zmeny e-mailu pre váš účet.'
        : 'Použite tento jednorazový kód na overenie e-mailovej adresy pre váš účet.';
    $appName = (string) config('app.name', 'Astrokomunita');
    $frontendUrl = rtrim((string) env('FRONTEND_URL', config('app.url', 'http://localhost')), '/');
    $logoUrl = $frontendUrl . '/logo.png';
    $ttlMinutes = max(5, (int) config('email_verification.code_ttl_minutes', 20));
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
    Váš jednorazový kód na dokončenie overenia e-mailu.
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
                            Ak ste túto akciu nevyžiadali, tento e-mail môžete bezpečne ignorovať.
                        </p>
                        <p style="margin:0;font-size:13px;line-height:1.6;color:#ABB8C9;">
                            Ak e-mail nevidíte v doručenej pošte, skontrolujte prosím aj spam alebo priečinok Reklama.
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
