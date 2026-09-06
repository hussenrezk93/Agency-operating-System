{{--
    DRAFT — not wired to any Mailable yet. No "send a welcome email on signup" feature
    exists in the app (UserService only ever triggers EmailVerificationMail). Kept ready
    for whenever that feature gets built: pass $firstName, $email, $actionUrl,
    $roleLabel, $roleDescription. Layout modeled on a reference design the user liked
    (Hostinger's "invitation to collaborate" email): centered icon+wordmark, large bold
    centered heading, solid pill button, and a tinted highlight box — reused here as the
    "your role" box instead of an access-grant list, since Agency OS has roles, not shared
    hosting access.
--}}
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title>Welcome to Agency OS</title>
<!--[if mso]><style>table{border-collapse:collapse}td{font-family:Arial,sans-serif}</style><![endif]-->
<style>@media screen and (max-width:620px){.shell{width:100%!important}.pad{padding-left:24px!important;padding-right:24px!important}}</style>
</head>
<body style="margin:0;padding:0;background:#f4f4f2">
<div style="display:none;font-size:1px;color:#f4f4f2;max-height:0;overflow:hidden">Your Agency OS account is ready.&nbsp;&zwnj;&nbsp;&zwnj;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f2">
<tr><td align="center" style="padding:40px 12px">
<table class="shell" role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#fff;border-radius:16px;overflow:hidden">

<tr><td class="pad" align="center" style="padding:48px 40px 32px">
<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
<td valign="middle"><span style="font-family:'Segoe UI',Arial,sans-serif;font-size:20px;font-weight:700;color:#ffffff;letter-spacing:.4px">Agency<span style="color:#FC6E20">OS</span></span></td>
<td valign="middle" style="padding-left:10px;font-family:Arial,sans-serif;font-size:20px;font-weight:800;color:#233D4D;letter-spacing:-.02em">Agency OS</td>
</tr></table>
</td></tr>

<tr><td class="pad" align="center" style="padding:0 40px 8px">
<h1 style="margin:0 0 16px;font-family:Arial,sans-serif;font-size:34px;line-height:1.15;font-weight:800;color:#1A1A24">Welcome to<br>Agency OS.</h1>
<p style="margin:0 auto;max-width:420px;font-family:Arial,sans-serif;font-size:16px;line-height:25px;color:#54545c">Hi {{ $firstName }}, your account is ready. Agency OS keeps every task, department, and deadline in one clear view.</p>
</td></tr>

<tr><td align="center" style="padding:32px 40px 8px">
<a href="{{ $actionUrl }}" style="display:inline-block;background:#FC6E20;border-radius:10px;color:#fff;font-family:Arial,sans-serif;font-size:16px;font-weight:700;line-height:52px;text-align:center;text-decoration:none;padding:0 40px">Open Agency OS</a>
</td></tr>

<tr><td align="center" style="padding:0 40px 32px">
<p style="margin:0;font-family:Arial,sans-serif;font-size:13px;color:#9a9aa2">You can sign in with {{ $email }} any time.</p>
</td></tr>

<tr><td class="pad" style="padding:0 40px 40px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFF3EA;border-radius:14px">
<tr><td style="padding:20px 24px">
<p style="margin:0 0 4px;font-family:Arial,sans-serif;font-size:11px;font-weight:800;letter-spacing:1px;color:#FC6E20;text-transform:uppercase">Your role</p>
<p style="margin:0 0 4px;font-family:Arial,sans-serif;font-size:17px;font-weight:800;color:#233D4D">{{ $roleLabel }}</p>
<p style="margin:0;font-family:Arial,sans-serif;font-size:14px;line-height:21px;color:#6b6b74">{{ $roleDescription }}</p>
</td></tr>
</table>
</td></tr>

<tr><td class="pad" align="center" style="padding:24px 40px 36px;border-top:1px solid #eeeeec">
<p style="margin:0;font-family:Arial,sans-serif;font-size:12px;line-height:18px;color:#9a9aa2">Need a hand? <a href="mailto:{{ config('mail.from.address') }}" style="color:#233D4D;font-weight:700;text-decoration:none">Contact support</a> &middot; &copy; {{ now()->year }} Agency OS</p>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
