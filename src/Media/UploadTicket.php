<?php

namespace Tey\Kit\Media;

use DateTimeInterface;
use Tey\Kit\Media\Models\MediaUpload;

/**
 * What the client does next. `PUT` sends the body straight to `url` with exactly `headers`; `POST` sends it as multipart to the app's own fallback
 * route (the disk cannot presign); `null` means the content is already stored and ready (a dedupe hit), so the client confirms at once.
 * `MULTIPART` (opt-in, docs/media.md › Large files) is an S3 multipart upload: `upload_id`, and one entry per part with its presigned `url` and
 * `headers`, or a null `url` while the server still needs the parts' SHA-256s (the client asks again with `part_sha256`).
 */
final readonly class UploadTicket
{
    /**
     * @param  array<string, string>  $headers
     * @param  list<array{part: int, url: ?string, headers: array<string, string>}>  $parts
     */
    public function __construct(
        public MediaUpload $upload,
        public ?string $method,
        public ?string $url = null,
        public array $headers = [],
        public ?DateTimeInterface $expiresAt = null,
        public ?string $uploadId = null,
        public array $parts = [],
    ) {}

    /** @return array{id: string, status: string, method: ?string, url: ?string, headers: array<string, string>, expires_at: ?string, upload_id?: string, parts?: list<array{part: int, url: ?string, headers: array<string, string>}>} */
    public function toArray(): array
    {
        $ticket = ['id' => $this->upload->id, 'status' => $this->upload->status, 'method' => $this->method, 'url' => $this->url, 'headers' => $this->headers, 'expires_at' => $this->expiresAt?->format(DATE_ATOM)];

        // Only a multipart ticket carries the extra keys, so every other ticket serialises exactly as before.
        return $this->method === 'MULTIPART' ? [...$ticket, 'upload_id' => $this->uploadId, 'parts' => $this->parts] : $ticket;
    }
}
