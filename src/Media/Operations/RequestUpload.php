<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Tey\Kit\Media\MediaRules;
use Tey\Kit\Media\Models\MediaUpload;
use Tey\Kit\Media\UploadTicket;

/**
 * Step one of an upload: the client declares the type, size and SHA-256 of what it will send; the declaration is checked against the collection's
 * rules and a pending record is reserved. The ticket is a presigned PUT whose signature binds Content-Type, Content-Length, the SHA-256 checksum and
 * the immutable Cache-Control, so the bucket refuses any other body. A disk that cannot presign gets a `POST` ticket for the app's multipart route.
 *
 * Authorization is the caller's (a policy check in the controller, MCP tool or job) before this runs. Replaying a `mutationId` with the same
 * declaration returns the same record and a fresh URL; with a different declaration it is refused. Content already ready in the scope is reused.
 */
class RequestUpload
{
    use AsAction;

    public const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function handle(string $scope, MediaRules $rules, string $mime, int $bytes, string $sha256, ?string $mutationId = null): UploadTicket
    {
        $sha256 = strtolower($sha256);
        $errors = array_filter([
            'mime' => $rules->accepts($mime) ? null : 'That type of file is not accepted.',
            'bytes' => $bytes > 0 && $bytes <= $rules->maxBytes ? null : 'The file may be at most '.intdiv($rules->maxBytes, 1024).' KB.',
            'sha256' => preg_match('/^[0-9a-f]{64}$/', $sha256) === 1 ? null : 'The checksum must be a hex SHA-256.',
        ]);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        $declared = ['disk' => $rules->disk, 'mime' => $mime, 'bytes' => $bytes, 'sha256' => $sha256];
        if ($mutationId !== null && ($replay = MediaUpload::query()->where('scope', $scope)->where('mutation_id', $mutationId)->first()) !== null) {
            if (array_diff_assoc($declared, $replay->only(array_keys($declared))) !== []) {
                throw new RuntimeException('media.upload.mutation_conflict');
            }

            return $this->ticket($replay, $rules);
        }
        $existing = MediaUpload::query()->where('scope', $scope)->where('sha256', $sha256)->where('disk', $rules->disk)->where('status', 'ready')->first();
        $upload = MediaUpload::query()->create([
            'scope' => $scope, 'mutation_id' => $mutationId, 'key' => $rules->key($sha256, $mime), ...$declared,
            'status' => $existing !== null ? 'ready' : 'pending', 'width' => $existing?->width, 'height' => $existing?->height,
        ]);

        return $this->ticket($upload, $rules);
    }

    private function ticket(MediaUpload $upload, MediaRules $rules): UploadTicket
    {
        if ($upload->ready()) {
            return new UploadTicket($upload, null);
        }
        $disk = Storage::disk($upload->disk);
        if (! $disk->providesTemporaryUploadUrls()) {
            return new UploadTicket($upload, 'POST');
        }
        $expiresAt = now()->addSeconds($rules->ttlSeconds);
        $headers = ['Content-Type' => $upload->mime, 'Cache-Control' => self::CACHE_CONTROL, 'x-amz-checksum-sha256' => base64_encode((string) hex2bin($upload->sha256))];
        $signed = $disk->temporaryUploadUrl($upload->key, $expiresAt, [
            'ContentType' => $upload->mime, 'ContentLength' => $upload->bytes, 'CacheControl' => self::CACHE_CONTROL,
            'ChecksumAlgorithm' => 'SHA256', 'ChecksumSHA256' => $headers['x-amz-checksum-sha256'],
        ]);
        // The browser sets Host and Content-Length itself (it may not set them); every other signed header is the client's to send exactly.
        foreach ($signed['headers'] ?? [] as $name => $value) {
            if (! in_array(strtolower($name), ['host', 'content-length'], true)) {
                $headers[$name] = is_array($value) ? implode(', ', $value) : (string) $value;
            }
        }

        return new UploadTicket($upload, 'PUT', $signed['url'], $headers, $expiresAt);
    }
}
