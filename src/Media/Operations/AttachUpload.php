<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Media\KeyPathGenerator;
use Tey\Kit\Media\Models\MediaUpload;

/**
 * Attaches a ready upload to a model as a Media item over the existing key: a metadata row only, with no byte copy. The item is marked as not owning
 * its object (KeptFileRemover never deletes it), so several owners may reference the same content. Attaching the same upload to the same owner and
 * collection again returns the first item.
 */
class AttachUpload
{
    use AsAction;

    /** @param  HasMedia&Model  $owner */
    public function handle(MediaUpload $upload, HasMedia $owner, string $collection, ?string $name = null): Media
    {
        if (! $upload->ready()) {
            throw new RuntimeException('media.upload.pending');
        }
        $existing = $owner->media()->where('collection_name', $collection)->get()->first(fn (Media $media): bool => $media->getCustomProperty(KeyPathGenerator::UPLOAD) === $upload->id);
        if ($existing !== null) {
            return $existing;
        }
        $class = config('media-library.media_model', Media::class);
        /** @var Media $media */
        $media = new $class;
        $media->forceFill([
            'model_type' => $owner->getMorphClass(), 'model_id' => $owner->getKey(), 'uuid' => (string) Str::uuid(), 'collection_name' => $collection,
            'name' => $name ?? pathinfo($upload->key, PATHINFO_FILENAME), 'file_name' => basename($upload->key), 'mime_type' => $upload->mime,
            'disk' => $upload->disk, 'conversions_disk' => $upload->disk, 'size' => $upload->bytes, 'manipulations' => [], 'generated_conversions' => [], 'responsive_images' => [],
            'custom_properties' => array_filter([KeyPathGenerator::KEY => $upload->key, KeyPathGenerator::UPLOAD => $upload->id, 'sha256' => $upload->sha256, 'width' => $upload->width, 'height' => $upload->height], fn ($value) => $value !== null),
        ])->save();

        return $media;
    }
}
