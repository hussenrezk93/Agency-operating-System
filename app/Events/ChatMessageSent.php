<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly ChatMessage $message) {}
}
