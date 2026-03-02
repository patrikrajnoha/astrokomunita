<!doctype html>
<html lang="sk">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Newsletter</title>
</head>
<body style="margin:0;padding:0;background:#0b1220;color:#e5ecff;font-family:InterVariable,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Liberation Sans',Helvetica,Arial,sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px;background:#0b1220;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#121a2a;border:1px solid #263247;border-radius:14px;overflow:hidden;">
          <tr>
            <td style="padding:24px;">
              <h1 style="margin:0 0 12px;font-size:26px;line-height:1.2;color:#ffffff;">
                @if($alreadyUnsubscribed)
                  Newsletter bol uz odhlaseny
                @else
                  Odhlasenie prebehlo uspesne
                @endif
              </h1>
              <p style="margin:0;font-size:15px;line-height:1.6;color:#dbeafe;">
                @if($alreadyUnsubscribed)
                  Tento ucet uz nema zapnuty tyzdenny newsletter.
                @else
                  Uz vam nebudeme posielat tyzdenny newsletter.
                @endif
              </p>
              <p style="margin:14px 0 0;font-size:13px;line-height:1.5;color:#9fb2d1;">
                Ak si to rozmyslite, newsletter viete znovu zapnut v nastaveniach profilu.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
