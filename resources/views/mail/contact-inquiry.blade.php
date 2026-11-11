<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#0c0c0c;color:#f7f1e4;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0c0c0c;padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#141414;border-radius:16px;padding:32px 28px;">
          <tr>
            <td style="font-family:Arial,sans-serif;">
              <p style="margin:0;letter-spacing:0.18em;text-transform:uppercase;font-size:12px;color:#e2b657;">BhuriBhoj</p>
              <h1 style="margin:12px 0 0;font-size:26px;font-weight:normal;color:#f7f1e4;font-family:Georgia,serif;">New contact inquiry</h1>
              <p style="margin:20px 0 0;font-size:14px;line-height:1.7;color:#cbbfa6;"><strong style="color:#f7f1e4;">Name:</strong> {{ $inquiry['name'] }}</p>
              <p style="margin:8px 0 0;font-size:14px;line-height:1.7;color:#cbbfa6;"><strong style="color:#f7f1e4;">Mess:</strong> {{ $inquiry['mess'] }}</p>
              <p style="margin:8px 0 0;font-size:14px;line-height:1.7;color:#cbbfa6;"><strong style="color:#f7f1e4;">Members:</strong> {{ $inquiry['members'] }}</p>
              @if (! empty($inquiry['phone']))
                <p style="margin:8px 0 0;font-size:14px;line-height:1.7;color:#cbbfa6;"><strong style="color:#f7f1e4;">Phone:</strong> {{ $inquiry['phone'] }}</p>
              @endif
              @if (! empty($inquiry['email']))
                <p style="margin:8px 0 0;font-size:14px;line-height:1.7;color:#cbbfa6;"><strong style="color:#f7f1e4;">Email:</strong> {{ $inquiry['email'] }}</p>
              @endif
              <p style="margin:20px 0 0;font-size:14px;line-height:1.7;color:#cbbfa6;"><strong style="color:#f7f1e4;">Message</strong></p>
              <p style="margin:8px 0 0;font-size:15px;line-height:1.7;color:#f7f1e4;white-space:pre-wrap;">{{ $inquiry['message'] }}</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
