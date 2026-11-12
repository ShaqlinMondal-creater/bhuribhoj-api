<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#0c0c0c;color:#f7f1e4;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0c0c0c;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#141414;border-radius:16px;padding:32px 28px;">
          <tr>
            <td style="font-family:Arial,sans-serif;">
              <p style="margin:0;letter-spacing:0.18em;text-transform:uppercase;font-size:12px;color:#e2b657;">BhuriBhoj · by liwaas</p>
              <h1 style="margin:12px 0 0;font-size:26px;font-weight:normal;color:#f7f1e4;font-family:Georgia,serif;">Reply from BhuriBhoj support</h1>
              <p style="margin:16px 0 0;font-size:15px;line-height:1.6;color:#cbbfa6;">Hello {{ $name }},</p>
              <p style="margin:12px 0 0;font-size:15px;line-height:1.7;color:#f7f1e4;white-space:pre-wrap;">{{ $reply }}</p>
              <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#8a7d68;">This is a reply to your message about <strong style="color:#cbbfa6;">{{ $mess }}</strong>.</p>
              @include('mail.partials.brand-footer')
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
