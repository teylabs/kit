<?php

namespace Tey\Kit\Media;

use DateTimeInterface;
use Tey\Kit\Media\Models\MediaUpload;

/**
 * What the client does next. `PUT` sends the body straight to `url` with exactly `headers`; `POST` sends it as multipart to the app's own fallback
 * route (the disk cannot presign); `null` means the content is already stored and ready (a dedupe hit), so the client confirms at once.
 */
final readonly class UploadTicket
{
    /** @param  array<string, string>  $headers */
    public function __construct(
        public MediaUpload $upload,
        public ?string $method,
        public ?string $url = null,
        public array $headers = [],
        public ?DateTimeInterface $expiresAt = null,
    ) {}

    /** @return array{id: string, status: string, method: ?string, url: ?string, headers: array<string, string>, expires_at: ?string} */
    public function toArray(): array
    {
        return ['id' => $this->upload->id, 'status' => $this->upload->status, 'method' => $this->method, 'url' => $this->url, 'headers' => $this->headers, 'expires_at' => $this->expiresAt?->format(DATE_ATOM)];
    }
}
