<?php

namespace Database\Factories;

use App\Models\ChatConversation;
use App\Models\ChatMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ChatMember> */
class ChatMemberFactory extends Factory
{
    protected $model = ChatMember::class;

    public function definition(): array
    {
        return [
            'conversation_id' => ChatConversation::factory(),
            'user_id' => User::factory(),
            'joined_at' => now(),
            'left_at' => null,
        ];
    }

    public function left(): static
    {
        return $this->state(fn (): array => ['left_at' => now()]);
    }
}
