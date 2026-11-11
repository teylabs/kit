<?php

namespace Tey\Kit\Media;

use DateTimeInterface;
use Illuminate\Filesystem\AwsS3V3Adapter;

/**
 * S3 multipart through the disk's own client, bucket and key prefix (needs `league/flysystem-aws-s3-v3`, which brings `aws/aws-sdk-php`).
 */
final class S3MultipartStore implements MultipartStore
{
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

    /** @return array{Bucket: string, Key: string} */
    private function target(string $key): array
    {
        return ['Bucket' => (string) $this->disk->getConfig()['bucket'], 'Key' => $this->disk->path($key)];
    }
}
