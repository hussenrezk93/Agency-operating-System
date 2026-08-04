<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('agencyos.notifications.email.chat_digest_subject', ['count' => $batch->message_count]) }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a; background: #f5f5f5; padding: 24px 0; margin: 0">
    <table role="presentation" width="100%" style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden">
        <tr>
            <td style="background: #0f172a; color: #ffffff; padding: 18px 24px; font-size: 18px; font-weight: bold">
                {{ config('mail.from.name') }}
            </td>
        </tr>
        <tr>
            <td style="padding: 24px">
                <h1 style="font-size: 18px; margin: 0 0 16px">{{ __('agencyos.notifications.email.chat_digest_heading') }}</h1>
                <ul style="padding: 0 0 0 18px; margin: 0">
                    @foreach($messages as $message)
                        <li style="font-size: 14px; line-height: 1.8; color: #374151">
                            <strong>{{ $message->conversation->title ?? __('agencyos.chat.type.'.$message->conversation->type->value) }}</strong>
                            — {{ $message->sender->full_name }}:
                            {{ $message->body ?? $message->link_url }}
                        </li>
                    @endforeach
                </ul>
            </td>
        </tr>
        <tr>
            <td style="padding: 16px 24px; background: #f9fafb; font-size: 12px; color: #9ca3af">
                {{ __('agencyos.notifications.email.footer') }}
            </td>
        </tr>
    </table>
</body>
</html>
