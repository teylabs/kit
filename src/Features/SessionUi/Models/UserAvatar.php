<?php

namespace Tey\Kit\Features\SessionUi\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Features\Core\Users;

/**
 * A user's avatar holder: the host's user row seen as a media owner, so the kit adds the `avatar` collection without the host's User model
 * changing. One file per user (replacing deletes the old one), jpeg, png or webp, and a square `thumb` plus a `thumb2x`, made at upload (never queued).
 */
class UserAvatar extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    /** The key as a string, so the media morph relation binds `model_id` as a string (varchar on string or uuid morphs; PostgreSQL refuses varchar = integer). */
    protected $keyType = 'string';

    public function getTable(): string
    {
        return (new (Users::model()))->getTable();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(Fit::Crop, 96, 96)->performOnCollections('avatar')->nonQueued();
        $this->addMediaConversion('thumb2x')->fit(Fit::Crop, 192, 192)->performOnCollections('avatar')->nonQueued();
    }
}
