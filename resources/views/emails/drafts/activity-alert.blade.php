{{--
    DRAFT — not wired to any Mailable yet. This app has no new-sign-in/suspicious-login
    detection at all (LoginController never records device/IP beyond a rate-limit key,
    no anomaly check, no alert dispatch) — building that is a separate security feature.
    Kept ready for whenever it exists: pass $signInTime, $device, $location, $actionUrl.
--}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Agency OS sign-in</title>
<style>@media screen and (max-width:620px){.shell{width:100%!important}.pad{padding-left:24px!important;padding-right:24px!important}}</style>
</head>
<body style="margin:0;padding:0;background:#f4f4f2">
<div style="display:none;font-size:1px;color:#f4f4f2;max-height:0;overflow:hidden">A new sign-in was detected on your account.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f2">
<tr><td align="center" style="padding:32px 12px">
<table class="shell" role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#fff;border-radius:16px;overflow:hidden">
<tr><td style="background:#233D4D">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td class="pad" style="padding:22px 40px">
<span style="font-family:'Segoe UI',Arial,sans-serif;font-size:20px;font-weight:700;color:#ffffff;letter-spacing:.4px">Agency<span style="color:#FC6E20">OS</span></span>
</td></tr></table>
</td></tr>
<tr><td class="pad" style="padding:36px 40px 8px">
<p style="margin:0 0 12px;font-family:Arial,sans-serif;font-size:12px;font-weight:bold;letter-spacing:1.2px;color:#FC6E20;text-transform:uppercase">Account security</p>
<h1 style="margin:0 0 12px;font-family:Arial,sans-serif;font-size:28px;line-height:36px;color:#233D4D">New sign-in detected.</h1>
<p style="margin:0;font-family:Arial,sans-serif;font-size:16px;line-height:25px;color:#555560">We noticed a sign-in to your account. Here are the details:</p>
</td></tr>
<tr><td class="pad" style="padding:24px 40px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f7f5;border-radius:8px">
<tr><td style="padding:18px 20px;font-family:Arial,sans-serif;font-size:14px;line-height:23px;color:#555560">
<strong style="color:#233D4D">When</strong>&nbsp;&nbsp;{{ $signInTime }}<br>
<strong style="color:#233D4D">Device</strong>&nbsp;&nbsp;{{ $device }}<br>
<strong style="color:#233D4D">Location</strong>&nbsp;&nbsp;{{ $location }}
</td></tr>
</table>
<p style="margin:22px 0 0;font-family:Arial,sans-serif;font-size:15px;line-height:23px;color:#555560">Wasn&rsquo;t you? Secure your account now.</p>
<a href="{{ $actionUrl }}" style="display:inline-block;margin-top:14px;background:#FC6E20;border-radius:6px;color:#fff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;line-height:48px;text-align:center;text-decoration:none;width:184px">Review activity</a>
</td></tr>
<tr><td class="pad" style="padding:24px 40px 36px">
<p style="margin:0;font-family:Arial,sans-serif;font-size:12px;line-height:18px;color:#96969e">&copy; {{ now()->year }} Agency OS &middot; <a href="mailto:{{ config('mail.from.address') }}" style="color:#777781">Security support</a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
