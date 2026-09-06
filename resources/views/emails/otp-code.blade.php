<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title ?? 'OTP Code' }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2f5;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#eef2f5;padding:28px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 28px rgba(15,55,85,0.08);">
          {{-- Header --}}
          <tr>
            <td align="center" style="background:#00a78b;padding:28px 24px 24px;">
              <div style="font-size:28px;line-height:1.2;font-weight:700;color:#ffffff;letter-spacing:0.2px;">
                Softifybd
              </div>
              <div style="margin-top:8px;font-size:15px;line-height:1.4;font-weight:500;color:#ffffff;">
                {{ $subtitle ?? 'Register OTP Code' }}
              </div>
            </td>
          </tr>

          {{-- Body --}}
          <tr>
            <td style="padding:28px 28px 8px;color:#334155;">
              <p style="margin:0 0 14px;font-size:15px;line-height:1.5;color:#1f2937;">
                Hi {{ $name ?? 'there' }},
              </p>
              <p style="margin:0 0 22px;font-size:15px;line-height:1.55;color:#64748b;">
                {{ $intro ?? 'Your One-Time Password (OTP) for email verification is:' }}
              </p>

              {{-- OTP box --}}
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td align="center" style="padding:0 0 8px;">
                    <div style="display:inline-block;min-width:220px;padding:18px 28px;border:1.5px dashed #00a78b;border-radius:10px;background:#f3fbf9;">
                      <div style="font-size:34px;line-height:1.2;font-weight:700;letter-spacing:6px;color:#00a78b;">
                        {{ $code }}
                      </div>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td align="center" style="padding:0 0 22px;">
                    <div style="font-size:12px;line-height:1.4;color:#94a3b8;">
                      Double Tap to copy the OTP
                    </div>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 12px;font-size:14px;line-height:1.6;color:#64748b;">
                This code is valid for <strong style="color:#1f2937;">{{ $expiresInMinutes ?? 5 }} minutes</strong>.
                Please don't share it with anyone for your security.
              </p>
              <p style="margin:0 0 22px;font-size:14px;line-height:1.6;color:#64748b;">
                If you didn't request this, you can safely ignore this email. No changes will be made without verification.
              </p>

              {{-- Help box --}}
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 22px;">
                <tr>
                  <td style="background:#f1f5f9;border-left:4px solid #00a78b;border-radius:6px;padding:14px 16px;">
                    <div style="font-size:14px;line-height:1.5;color:#475569;">
                      <strong style="color:#1f2937;">Need help?</strong>
                      Reach out to our support team anytime.
                    </div>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 8px;font-size:14px;line-height:1.5;color:#64748b;">
                — The Biznify Team
              </p>
            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td align="center" style="background:#f8fafc;padding:16px 24px;border-top:1px solid #eef2f7;">
              <div style="font-size:12px;line-height:1.5;color:#94a3b8;">
                © {{ date('Y') }} Softifybd. All rights reserved.
              </div>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
