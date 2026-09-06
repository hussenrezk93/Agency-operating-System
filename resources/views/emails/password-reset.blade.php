<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ __('agencyos.password_reset.mail_subject') }}</title>
<style>@media screen and (max-width:620px){.shell{width:100%!important}.pad{padding-left:24px!important;padding-right:24px!important}}</style>
</head>
<body style="margin:0;padding:0;background:#f4f4f2">
<div style="display:none;font-size:1px;color:#f4f4f2;max-height:0;overflow:hidden">{{ __('agencyos.password_reset.mail_body') }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f2">
<tr><td align="center" style="padding:32px 12px">
<table class="shell" role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#fff;border-radius:16px;overflow:hidden">
<tr><td style="background:#233D4D">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td class="pad" style="padding:22px 40px">
<span style="font-family:'Segoe UI',Arial,sans-serif;font-size:20px;font-weight:700;color:#ffffff;letter-spacing:.4px">Agency<span style="color:#FC6E20">OS</span></span>
</td></tr></table>
</td></tr>
<tr><td class="pad" style="padding:36px 40px 8px">
<table role="presentation" cellpadding="0" cellspacing="0"><tr>
<td width="48" height="48" align="center" style="background:#FC6E20;border-radius:24px;font-family:Arial,sans-serif;font-size:22px;line-height:48px;color:#fff">&#128269;</td>
<td style="padding-left:12px;font-family:Arial,sans-serif;font-size:12px;font-weight:bold;letter-spacing:1.2px;color:#FC6E20;text-transform:uppercase">Agency OS</td>
</tr></table>
<h1 style="margin:20px 0 12px;font-family:Arial,sans-serif;font-size:28px;line-height:36px;color:#233D4D">{{ __('agencyos.password_reset.mail_heading') }}</h1>
<p style="margin:0;font-family:Arial,sans-serif;font-size:16px;line-height:25px;color:#555560">{{ __('agencyos.password_reset.mail_body') }}</p>
</td></tr>
<tr><td class="pad" style="padding:28px 40px 12px">
<a href="{{ $resetUrl }}" style="display:inline-block;background:#FC6E20;border-radius:6px;color:#fff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;line-height:48px;text-align:center;text-decoration:none;width:178px">{{ __('agencyos.password_reset.mail_button') }}</a>
<p style="margin:22px 0 0;font-family:Arial,sans-serif;font-size:13px;line-height:20px;color:#777781">{{ __('agencyos.password_reset.mail_expiry') }}</p>
</td></tr>
<tr><td class="pad" style="padding:24px 40px 36px">
<p style="margin:0;font-family:Arial,sans-serif;font-size:12px;line-height:18px;color:#96969e">&copy; {{ now()->year }} Agency OS &middot; <a href="mailto:{{ config('mail.from.address') }}" style="color:#777781">{{ __('agencyos.password_reset.mail_support') }}</a></p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
