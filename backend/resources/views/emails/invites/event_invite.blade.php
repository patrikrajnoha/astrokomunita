@php
    $appName = (string) config('app.name', 'Astrokomunita');
    $frontendUrl = rtrim((string) env('FRONTEND_URL', config('app.url', 'http://localhost')), '/');
    $logoUrl = $frontendUrl . '/logo.png';

    $event = $invite->event;
    $inviter = $invite->inviter;

    $eventTitle = $event ? (string) $event->title : 'Astronomické podujatie';
    $inviterName = $inviter ? (string) ($inviter->name ?: $inviter->username) : $appName;
    $attendeeName = (string) $invite->attendee_name;
    $personalMessage = $invite->message ? trim((string) $invite->message) : null;

    $ticketUrl = $invite->token
        ? $frontendUrl . '/invites/public/' . urlencode((string) $invite->token)
        : null;
@endphp
<!doctype html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pozvánka na {{ $eventTitle }}</title>
</head>
<body style="margin:0;padding:0;background:#151d28;font-family:Arial,Helvetica,sans-serif;">
<span style="display:none !important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">
    Pozvánka na astronomické podujatie od {{ $inviterName }}.
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
                            Máte pozvánku
                        </h1>
                        <p style="margin:0 0 24px;font-size:15px;line-height:1.6;color:#ABB8C9;">
                            {{ $inviterName }} vás pozýva na astronomické podujatie.
                        </p>

                        {{-- Event card --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                            <tr>
                                <td style="padding:20px 20px;background:#151d28;border-radius:14px;">
                                    <p style="margin:0 0 4px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:#0F73FF;">
                                        ✦ Astrokomunita
                                    </p>
                                    <p style="margin:0 0 6px;font-size:18px;line-height:1.3;font-weight:700;color:#ffffff;">
                                        {{ $eventTitle }}
                                    </p>
                                    <p style="margin:0;font-size:13px;line-height:1.5;color:#ABB8C9;">
                                        Vstupenka pre: <strong style="color:#ffffff;">{{ $attendeeName }}</strong>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        @if ($personalMessage)
                        <p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#ABB8C9;">Osobná správa:</p>
                        <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#ffffff;font-style:italic;">
                            „{{ $personalMessage }}"
                        </p>
                        @endif

                        @if ($ticketUrl)
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;">
                            <tr>
                                <td align="center">
                                    <a href="{{ $ticketUrl }}" style="display:inline-block;padding:13px 28px;background:#0F73FF;color:#ffffff;text-decoration:none;border-radius:999px;font-size:15px;font-weight:600;">
                                        Zobraziť pozvánku
                                    </a>
                                </td>
                            </tr>
                        </table>
                        @endif

                        <p style="margin:0;font-size:13px;line-height:1.6;color:#ABB8C9;">
                            Ak ste túto pozvánku dostali omylom, môžete ju bezpečne ignorovať.
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
