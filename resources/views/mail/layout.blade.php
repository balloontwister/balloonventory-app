<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('title', config('app.name'))</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <![endif]-->
    <style>
        /* Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        /* Outlook link fix */
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
        /* Responsive */
        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .content-pad { padding: 24px 20px !important; }
            .header-pad { padding: 20px 20px 16px !important; }
            .footer-pad { padding: 16px 20px 24px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#F4F4F5;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">

<!-- Preview text (hidden, shows in inbox preview) -->
@hasSection('preview')
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">@yield('preview')&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
@endif

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F4F4F5;">
    <tr>
        <td align="center" style="padding:32px 16px;">

            <table class="email-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">

                <!-- ── Header ───────────────────────────────────────────── -->
                <tr>
                    <td class="header-pad" style="background-color:#FFFFFF;border-radius:14px 14px 0 0;padding:28px 40px 20px;border-bottom:1px solid #E4E4E7;">
                        <p style="margin:0;font-size:20px;font-weight:700;color:#0A0A0A;letter-spacing:-0.02em;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">{{ __('email.brand') }}</p>
                        <p style="margin:4px 0 0;font-size:11px;font-weight:600;color:#A1A1AA;text-transform:uppercase;letter-spacing:0.08em;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">{{ __('email.from_tallie') }}</p>
                    </td>
                </tr>

                <!-- ── Body ─────────────────────────────────────────────── -->
                <tr>
                    <td class="content-pad" style="background-color:#FFFFFF;padding:32px 40px;">
                        @yield('content')
                    </td>
                </tr>

                <!-- ── Footer ───────────────────────────────────────────── -->
                <tr>
                    <td class="footer-pad" style="background-color:#FFFFFF;border-radius:0 0 14px 14px;padding:20px 40px 28px;border-top:1px solid #E4E4E7;">
                        <p style="margin:0;font-size:12px;line-height:1.6;color:#A1A1AA;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;">
                            {{ __('email.footer_account') }}<br>
                            {{ __('email.footer_rights', ['year' => date('Y')]) }}
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
