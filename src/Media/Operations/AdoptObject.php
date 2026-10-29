<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Media\KeyPathGenerator;
use Tey\Kit\Media\ObjectProbe;

/**
 * Adopts an object that already exists on a disk as a Media item over its key: a metadata row from a probe of its size and first bytes, with no copy
 * or move, and the item never owns the object (KeptFileRemover). Its conversions are queued. Adopting the same key into the same owner and collection
 * again returns the first item; an absent object is refused with `media.adopt.missing`.
 */
class AdoptObject
{
    use AsAction;

    /** @param  HasMedia&Model  $owner */
    public function handle(HasMedia $owner, string $collection, string $disk, string $key): Media
    {
        $existing = self::adopted($owner, $collection, $disk, $key);
        if ($existing !== null) {
            return $existing;
        }
        $storage = Storage::disk($disk);
        if (! $storage->exists($key)) {
            throw new RuntimeException('media.adopt.missing');
        }
        $probe = ObjectProbe::probe($storage, $key);
        $class = config('media-library.media_model', Media::class);
        /** @var Media $media */
        $media = new $class;
        $media->forceFill([
            'model_type' => $owner->getMorphClass(), 'model_id' => $owner->getKey(), 'uuid' => (string) Str::uuid(), 'collection_name' => $collection,
            'name' => pathinfo($key, PATHINFO_FILENAME), 'file_name' => basename($key), 'mime_type' => $probe['mime'],
            'disk' => $disk, 'conversions_disk' => $disk, 'size' => $probe['bytes'], 'manipulations' => [], 'generated_conversions' => [], 'responsive_images' => [],
            'custom_properties' => array_filter([KeyPathGenerator::KEY => $key, 'width' => $probe['width'], 'height' => $probe['height']], fn ($value) => $value !== null),
        ])->save();
        app(FileManipulator::class)->createDerivedFiles($media);

        return $media;
    }

    /** @param  HasMedia&Model  $owner */
    public static function adopted(HasMedia $owner, string $collection, string $disk, string $key): ?Media
    {
        return $owner->media()->where('collection_name', $collection)->where('disk', $disk)->get()
            ->first(fn (Media $media): bool => $media->getCustomProperty(KeyPathGenerator::KEY) === $key);
    }
}
