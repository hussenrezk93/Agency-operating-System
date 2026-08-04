<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * BRD §11.1 / §14 — chat notifications are immediate in APP but batched to EMAIL every two
 * hours, so a busy conversation cannot bury the task notifications that matter.
 *
 * The batch records exactly which messages it covered, so a message is never counted in two
 * digests. An empty window is never sent, and `chat_digest_count_check` enforces that at the
 * database level rather than trusting the scheduler (BRD §22.18).
 */
class ChatDigestBatch extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'status' => DeliveryStatus::class,
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'message_count' => 'integer',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(ChatMessage::class, 'chat_digest_batch_messages', 'batch_id', 'message_id')
            ->withPivot('added_at');
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Sent->value,
            'sent_at' => now(),
            'error' => null,
        ])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill(['status' => DeliveryStatus::Failed->value, 'error' => $error])->save();
    }
}
