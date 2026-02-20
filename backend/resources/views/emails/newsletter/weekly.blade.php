@php
  $weekStart = data_get($payload, 'week.start');
  $weekEnd = data_get($payload, 'week.end');
  $events = (array) data_get($payload, 'top_events', []);
  $articles = (array) data_get($payload, 'top_articles', []);
  $tip = (string) data_get($payload, 'astronomical_tip', '');
  $calendarUrl = (string) data_get($payload, 'cta.calendar_url', '#');
  $eventsUrl = (string) data_get($payload, 'cta.events_url', '#');
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Weekly Newsletter</title>
</head>
<body style="margin:0;padding:0;background:#0b1220;color:#e5ecff;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;background:#0b1220;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;background:#121a2a;border:1px solid #263247;border-radius:16px;overflow:hidden;">
          <tr>
            <td style="padding:24px;background:linear-gradient(135deg,#1f3c88,#0f7490);">
              <p style="margin:0 0 8px;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;color:#dbeafe;">Nebesky sprievodca</p>
              <h1 style="margin:0;font-size:28px;line-height:1.2;color:#ffffff;">Top udalosti buduceho tyzdna</h1>
              <p style="margin:10px 0 0;font-size:14px;line-height:1.5;color:#e2e8f0;">
                Prehlad na tyzden {{ $weekStart ?? '-' }} az {{ $weekEnd ?? '-' }}.
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:22px 24px 8px;">
              <h2 style="margin:0 0 10px;font-size:18px;color:#ffffff;">Top udalosti</h2>
              <ul style="margin:0;padding:0 0 0 18px;color:#dbeafe;">
                @forelse($events as $event)
                  <li style="margin-bottom:10px;line-height:1.45;">
                    <a href="{{ $event['url'] ?? '#' }}" style="color:#93c5fd;text-decoration:none;font-weight:700;">
                      {{ $event['title'] ?? 'Udalost' }}
                    </a>
                    <span style="display:block;font-size:13px;color:#9fb2d1;margin-top:2px;">
                      {{ $event['start_at'] ?? '' }}
                    </span>
                  </li>
                @empty
                  <li style="margin-bottom:10px;">Tento tyzden zatial nema vybrane udalosti.</li>
                @endforelse
              </ul>
            </td>
          </tr>

          <tr>
            <td style="padding:10px 24px 8px;">
              <h2 style="margin:0 0 10px;font-size:18px;color:#ffffff;">Najcitanejsie clanky (7 dni)</h2>
              <ul style="margin:0;padding:0 0 0 18px;color:#dbeafe;">
                @forelse($articles as $article)
                  <li style="margin-bottom:10px;line-height:1.45;">
                    <a href="{{ $article['url'] ?? '#' }}" style="color:#93c5fd;text-decoration:none;font-weight:700;">
                      {{ $article['title'] ?? 'Clanok' }}
                    </a>
                    <span style="display:block;font-size:13px;color:#9fb2d1;margin-top:2px;">
                      Citania: {{ (int) ($article['views'] ?? 0) }}
                    </span>
                  </li>
                @empty
                  <li style="margin-bottom:10px;">Za posledny tyzden este nie su dostupne clanky.</li>
                @endforelse
              </ul>
            </td>
          </tr>

          <tr>
            <td style="padding:12px 24px 6px;">
              <h2 style="margin:0 0 10px;font-size:18px;color:#ffffff;">Astronomicky tip tyzdna</h2>
              <p style="margin:0;font-size:14px;line-height:1.6;color:#dbeafe;">{{ $tip }}</p>
            </td>
          </tr>

          <tr>
            <td style="padding:18px 24px 28px;">
              <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;">
                <tr>
                  <td style="padding:0 8px 8px 0;">
                    <a href="{{ $calendarUrl }}" style="display:inline-block;padding:10px 14px;border-radius:10px;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">Open calendar</a>
                  </td>
                  <td style="padding:0 0 8px 8px;">
                    <a href="{{ $eventsUrl }}" style="display:inline-block;padding:10px 14px;border-radius:10px;background:#0f766e;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;">Browse events</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
