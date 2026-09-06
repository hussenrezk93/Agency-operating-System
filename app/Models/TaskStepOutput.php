<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Outputs are IMMUTABLE (BRD §13). A correction adds a new row and marks the old one
 * superseded; nothing is ever edited or deleted. `removed_at` follows the same
 * philosophy for a link the assignee wants to retract before the reviewer decides — it
 * marks the row, it never erases it.
 */
class TaskStepOutput extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_final' => 'boolean',
        'is_upload' => 'boolean',
        'created_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(TaskStep::class, 'task_step_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_output_id');
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_by_output_id !== null;
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    /** For an upload, `url` holds a relative path on the `public` disk instead of an
     *  external link — this resolves either shape to the one thing a view ever needs. */
    public function displayUrl(): string
    {
        return $this->is_upload ? Storage::disk('public')->url($this->url) : $this->url;
    }

    /** An upload can be a video instead of an image (product decision 2026-09) — the
     *  view uses this to render a <video> player instead of an <img> thumbnail. No
     *  stored mime type, so this reads the extension off the stored path/URL itself. */
    public function isVideo(): bool
    {
        return $this->is_upload && (bool) preg_match('/\.(mp4|mov|webm|m4v)$/i', $this->url);
    }
}
