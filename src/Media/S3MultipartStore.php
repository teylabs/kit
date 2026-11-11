<?php

namespace Tey\Kit\Media;

use Aws\S3\Exception\S3Exception;
use DateTimeInterface;
use Illuminate\Filesystem\AwsS3V3Adapter;

/**
 * S3 multipart through the disk's own client, bucket and key prefix (needs `league/flysystem-aws-s3-v3`, which brings `aws/aws-sdk-php`).
 *
 * `copyIfAbsent()` is one `CopyObject` with `If-None-Match: *` (S3 refuses it with 412 when the key exists). `CopyObject` stops at 5 GiB, so a
 * larger object is copied with the SDK's multipart copy after a HEAD, which can't be conditional.
 */
final class S3MultipartStore implements ConditionalCopyStore, MultipartStore
{
    /** The largest object one `CopyObject` copies. */
    private const COPY_LIMIT = 5 * 1024 * 1024 * 1024;

    public function __construct(private readonly AwsS3V3Adapter $disk) {}

    public function create(string $key, string $mime, string $cacheControl, bool $checksums): string
    {
        $result = $this->disk->getClient()->createMultipartUpload([
            ...$this->target($key), 'ContentType' => $mime, 'CacheControl' => $cacheControl, ...($checksums ? ['ChecksumAlgorithm' => 'SHA256'] : []),
        ]);

        return (string) $result['UploadId'];
    }

    public function presignPart(string $key, string $uploadId, int $part, int $bytes, ?string $sha256, DateTimeInterface $expiresAt): array
    {
        $client = $this->disk->getClient();
        $command = $client->getCommand('UploadPart', [
            ...$this->target($key), 'UploadId' => $uploadId, 'PartNumber' => $part, 'ContentLength' => $bytes,
            ...($sha256 !== null ? ['ChecksumAlgorithm' => 'SHA256', 'ChecksumSHA256' => $sha256] : []),
        ]);
        $request = $client->createPresignedRequest($command, $expiresAt);
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            if (! in_array(strtolower($name), ['host', 'content-length'], true)) {
                $headers[$name] = implode(', ', $values);
            }
        }

        return ['url' => (string) $request->getUri(), 'headers' => $headers];
    }

    public function complete(string $key, string $uploadId, array $parts): void
    {
        $this->disk->getClient()->completeMultipartUpload([...$this->target($key), 'UploadId' => $uploadId, 'MultipartUpload' => ['Parts' => array_map(
            fn (array $part): array => ['PartNumber' => $part['part'], 'ETag' => $part['etag'], ...($part['checksum'] !== null ? ['ChecksumSHA256' => $part['checksum']] : [])],
            $parts,
        )]]);
    }

    public function abort(string $key, string $uploadId): void
    {
        $this->disk->getClient()->abortMultipartUpload([...$this->target($key), 'UploadId' => $uploadId]);
    }

    public function copyIfAbsent(string $from, string $to, int $bytes): bool
    {
        $client = $this->disk->getClient();
        $source = $this->target($from);
        if ($bytes > self::COPY_LIMIT) {
            if ($this->disk->exists($to)) {
                return false;
            }
            $client->copy($source['Bucket'], $source['Key'], $source['Bucket'], $this->target($to)['Key']);

            return true;
        }
        try {
            $client->copyObject([...$this->target($to), 'CopySource' => rawurlencode($source['Bucket']).'/'.str_replace('%2F', '/', rawurlencode($source['Key'])), 'IfNoneMatch' => '*']);
        } catch (S3Exception $error) {
            if ($error->getStatusCode() === 412) {
                return false;
            }

            throw $error;
        }

        return true;
    }

    /** @return array{Bucket: string, Key: string} */
    private function target(string $key): array
    {
        return ['Bucket' => (string) $this->disk->getConfig()['bucket'], 'Key' => $this->disk->path($key)];
    }
}
