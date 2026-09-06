<?php

namespace App\Support;

use App\Models\ChatMessage;

/**
 * Shapes a ChatMessage into the JSON payload the near-real-time polling endpoint and the
 * classic-send JSON response both return — written once so the client-side renderer never
 * has to guess a shape that drifts from `resources/views/chat/index.blade.php`.
 */
final class ChatPresenter
{
    public static function initials(?string $name): string
    {
        return collect(preg_split('/\s+/u', trim($name ?? '')))
            ->filter()
            ->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->implode('');
    }

    /** @return array<string, mixed> */
    public static function messagePayload(ChatMessage $message, int $actorId): array
    {
        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender->full_name,
            'sender_initials' => self::initials($message->sender->full_name),
            'sender_avatar_url' => $message->sender->avatar_url,
            'is_mine' => $message->sender_id === $actorId,
            'is_deleted' => $message->isDeleted(),
            'deletable' => ! $message->isDeleted() && $message->sender_id === $actorId,
            'body' => $message->body,
            'link_url' => $message->link_url,
            'time' => $message->created_at->format('H:i'),
            'date_key' => $message->created_at->format('Y-m-d'),
            'date_label' => $message->created_at->translatedFormat('d M Y'),
            'delete_url' => $message->isDeleted() ? null : route('chat.messages.destroy', $message),
        ];
    }
}
