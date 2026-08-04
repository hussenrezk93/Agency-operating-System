<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChatMessage> */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => ChatConversation::factory(),
            'sender_id' => User::factory(),
            'body' => fake()->sentence(8),
            'link_url' => null,
            'created_at' => now(),
        ];
    }

    public function withLink(): static
    {
        return $this->state(fn (): array => [
            'link_url' => 'https://drive.google.com/file/d/'.fake()->lexify('??????????'),
        ]);
    }

    /**
     * BRD §14 — deletion CLEARS the content. `chat_msg_content_check` refuses a deleted row
     * that still holds text, so this state must null both columns.
     */
    public function deleted(?User $by = null): static
    {
        return $this->state(fn (): array => [
            'body' => null,
            'link_url' => null,
            'deleted_at' => now(),
            'deleted_by' => $by?->id ?? User::factory(),
        ]);
    }
}
