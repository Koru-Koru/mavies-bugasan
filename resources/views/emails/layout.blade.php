<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Mavies Bugasan')</title>
</head>
<body style="margin:0;padding:0;background:#F5FAF0;font-family:Arial,Helvetica,sans-serif;color:#1E1E1E;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5FAF0;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#FFFFFF;border:1px solid #E3EBD9;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td style="background:#5D8F2F;padding:20px 28px;">
                            <span style="color:#FFFFFF;font-size:20px;font-weight:bold;">Mavies Bugasan</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px 24px;color:#8A9A7E;font-size:12px;line-height:1.6;">
                            Mavies Bugasan at iba pa.<br>
                            This is an automated message &mdash; please do not reply.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
