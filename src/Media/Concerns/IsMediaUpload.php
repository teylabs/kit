<?php

namespace Tey\Kit\Media\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Tey\Kit\Media\MediaUploads;

/**
 * One declared upload: pending until its object is confirmed, then ready. The kit's behaviour for the upload model, on `kit.media.uploads_table`.
 * The record never owns the object's lifetime; a ready object may be shared by several Media items and is only ever removed by the app's own
 * reference-counted pruner.
 */
trait IsMediaUpload
{
    use HasUlids;

    public function initializeIsMediaUpload(): void
    {
        $this->guarded = [];
        $this->mergeCasts(['bytes' => 'integer', 'width' => 'integer', 'height' => 'integer']);
    }

    public function getTable(): string
    {
        return MediaUploads::table();
    }

    public function ready(): bool
    {
        return $this->status === 'ready';
    }
}
