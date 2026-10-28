<?php

namespace Tey\Kit\Media\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Media\MediaUploads;

/**
 * One declared upload: pending until its object is confirmed, then ready. The record never owns the object's lifetime; a ready object may be shared
 * by several Media items and is only ever removed by the app's own reference-counted pruner.
 *
 * @property string $id
 * @property string $scope
 * @property ?string $mutation_id
 * @property string $disk
 * @property string $key
 * @property string $mime
 * @property int $bytes
 * @property string $sha256
 * @property string $status
 * @property ?int $width
 * @property ?int $height
 */
class MediaUpload extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = ['bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];

    public function getTable(): string
    {
        return MediaUploads::table();
    }

    public function ready(): bool
    {
        return $this->status === 'ready';
    }
}
