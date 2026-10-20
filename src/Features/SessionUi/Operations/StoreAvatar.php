<?php

namespace Tey\Kit\Features\SessionUi\Operations;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tey\Kit\Features\SessionUi\Models\UserAvatar;

/**
 * Sets a user's avatar: validated (type by content, size, pixel count), then re-encoded as a square, so the stored original carries no EXIF or GPS and
 * is already right-way-up. Replaces any earlier avatar. The re-encode decodes the image, so the pixel count is checked first (a small file can be huge).
 */
class StoreAvatar
{
    use AsAction;

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function handle(UserAvatar $holder, UploadedFile $file): Media
    {
        $maxKb = (int) config('kit.avatar.max_kb', 2048);
        Validator::make(['avatar' => $file], ['avatar' => ['required', 'file', 'mimetypes:'.implode(',', array_keys(self::TYPES)), 'max:'.$maxKb]], ['avatar.mimetypes' => 'The photo must be a JPEG, PNG or WebP image.', 'avatar.max' => 'The photo may be at most '.$maxKb.' KB.'])->validate();
        $size = @getimagesize($file->getRealPath());
        if ($size === false || ! isset(self::TYPES[$size['mime']]) || $size[0] * $size[1] > (int) config('kit.avatar.max_pixels', 16_000_000)) {
            throw ValidationException::withMessages(['avatar' => 'That image is too large to use. Choose a smaller photo.']);
        }
        $extension = self::TYPES[$size['mime']];
        $path = $this->reencode($file->getRealPath(), $size['mime'], (int) config('kit.avatar.size', 1024));
        try {
            return $holder->addMedia($path)->usingFileName(Str::uuid().'.'.$extension)->toMediaCollection('avatar');
        } finally {
            @unlink($path);
        }
    }

    /** A centred square of at most `$side` pixels, upright, written fresh (GD never copies metadata). */
    private function reencode(string $source, string $mime, int $side): string
    {
        $image = match ($mime) {
            'image/png' => imagecreatefrompng($source),
            'image/webp' => imagecreatefromwebp($source),
            default => imagecreatefromjpeg($source),
        };
        if ($image === false) {
            throw ValidationException::withMessages(['avatar' => 'That image could not be read.']);
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (@exif_read_data($source)['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle !== 0) {
                $image = imagerotate($image, $angle, 0) ?: $image;
            }
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $edge = min($width, $height);
        $square = imagecrop($image, ['x' => intdiv($width - $edge, 2), 'y' => intdiv($height - $edge, 2), 'width' => $edge, 'height' => $edge]);
        $square = $edge > $side ? imagescale($square, $side, $side) : $square;
        $target = tempnam(sys_get_temp_dir(), 'kit-avatar');
        match ($mime) {
            'image/png' => imagepng($square, $target),
            'image/webp' => imagewebp($square, $target, 88),
            default => imagejpeg($square, $target, 88),
        };

        return $target;
    }
}
