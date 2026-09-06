<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TaskReferenceLink extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['is_upload' => 'boolean', 'created_at' => 'datetime'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** For an upload, `url` holds a relative path on the `public` disk instead of an
     *  external link — same convention as TaskStepOutput::displayUrl(). */
    public function displayUrl(): string
    {
        return $this->is_upload ? Storage::disk('public')->url($this->url) : $this->url;
    }

    /** Same convention as TaskStepOutput::isVideo() — a reference upload can be a video too. */
    public function isVideo(): bool
    {
        return $this->is_upload && (bool) preg_match('/\.(mp4|mov|webm|m4v)$/i', $this->url);
    }
}
