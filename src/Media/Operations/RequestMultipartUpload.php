<?php

namespace Tey\Kit\Media\Operations;

use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Tey\Kit\Media\MediaRules;
use Tey\Kit\Media\Models\MediaUpload;
use Tey\Kit\Media\MultipartStore;
use Tey\Kit\Media\MultipartUploads;
use Tey\Kit\Media\UploadTicket;
use Throwable;

/**
 * Step one for a host that offers S3 multipart (docs/media.md › Large files). It runs RequestUpload unchanged, so every check, replay and dedupe is
 * the same; then, only when `$multipart` is true (the client has a `complete` route) and MultipartUploads::eligible(), a pending upload's PUT ticket
 * becomes a MULTIPART one. Anything else is returned as RequestUpload made it.
 *
 * With part checksums on, the first call starts the upload and returns the parts with no URLs; the client hashes each part and asks again with the
 * same mutation id and `$partSha256`, and each part URL then signs its part's SHA-256. A replay reuses the started upload. Either way the whole
 * object is re-hashed on complete (CompleteMultipartUpload), so the part hashes are a transit check and never the proof of the declared SHA-256.
 */
final class RequestMultipartUpload
{
    use AsAction;

    /** @param  list<string>  $partSha256  hex SHA-256 per part, in order */
    public function handle(string $scope, MediaRules $rules, string $mime, int $bytes, string $sha256, ?string $mutationId = null, bool $multipart = false, array $partSha256 = []): UploadTicket
    {
        $ticket = RequestUpload::run($scope, $rules, $mime, $bytes, $sha256, $mutationId);
        $store = MultipartUploads::store($ticket->upload->disk);
        if (! $multipart || $ticket->method !== 'PUT' || ! MultipartUploads::eligible($ticket->upload->disk, $ticket->upload->bytes) || $store === null) {
            return $ticket;
        }
        $upload = $ticket->upload;
        $count = MultipartUploads::partCount($upload->bytes);
        $checksums = MultipartUploads::partChecksums();
        $hashes = $checksums && $partSha256 !== [] ? $this->hashes($partSha256, $count) : null;
        if ($upload->multipart_upload_id === null) {
            $id = $store->create(MultipartUploads::stagingKey($upload->key, $upload->id), $upload->mime, RequestUpload::CACHE_CONTROL, $checksums);
            // Two racing requests for one record: the first to record its id wins; the loser aborts its own and uses the winner's.
            $won = MediaUpload::query()->whereKey($upload->id)->whereNull('multipart_upload_id')->update(['multipart_upload_id' => $id]) === 1;
            if (! $won) {
                $this->abortQuietly($store, MultipartUploads::stagingKey($upload->key, $upload->id), $id);
            }
            $upload->refresh();
        }
        $uploadId = (string) $upload->multipart_upload_id;
        $expiresAt = now()->addSeconds($rules->ttlSeconds);
        $parts = [];
        try {
            for ($part = 1; $part <= $count; $part++) {
                $signed = $checksums && $hashes === null ? null
                    : $store->presignPart(MultipartUploads::stagingKey($upload->key, $upload->id), $uploadId, $part, MultipartUploads::partLength($upload->bytes, $part), $hashes === null ? null : base64_encode((string) hex2bin($hashes[$part - 1])), $expiresAt);
                $parts[] = ['part' => $part, 'url' => $signed['url'] ?? null, 'headers' => $signed['headers'] ?? []];
            }
        } catch (Throwable $error) {
            AbortMultipartUpload::run($upload);

            throw $error;
        }

        return new UploadTicket($upload, 'MULTIPART', null, [], $expiresAt, $uploadId, $parts);
    }

    /**
     * @param  array<mixed>  $hashes
     * @return list<string>
     */
    private function hashes(array $hashes, int $count): array
    {
        $hashes = array_values(array_map(fn ($hash) => is_string($hash) ? strtolower($hash) : '', $hashes));
        if (count($hashes) !== $count || array_filter($hashes, fn (string $hash): bool => preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) !== []) {
            throw ValidationException::withMessages(['part_sha256' => "Send a hex SHA-256 for each of the {$count} parts."]);
        }

        return $hashes;
    }

    private function abortQuietly(MultipartStore $store, string $key, string $id): void
    {
        try {
            $store->abort($key, $id);
        } catch (Throwable) {
            // The pruner aborts what is left.
        }
    }
}
