<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Password reset code</title>
</head>
<body style="margin:0;padding:24px;background:#0b1220;color:#e5e7eb;font-family:Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" style="max-width:560px;background:#111827;border-radius:14px;padding:24px;">
                    <tr>
                        <td>
                            <h1 style="margin:0 0 12px;font-size:22px;line-height:1.3;">Reset your password</h1>
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;">
                                Enter this one-time code in the password reset form:
                            </p>

                            <p style="margin:18px 0;padding:12px 16px;border:1px solid #334155;border-radius:10px;background:#0f172a;font-size:24px;letter-spacing:1px;font-weight:700;text-align:center;">
                                {{ $code }}
                            </p>

                            <p style="margin:0 0 8px;font-size:14px;line-height:1.6;">
                                If you did not request this reset, you can safely ignore this email.
                            </p>
                            <p style="margin:0;font-size:14px;line-height:1.6;color:#94a3b8;">
                                For security reasons, this code expires after a short period.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
