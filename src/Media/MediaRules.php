<?php

namespace Tey\Kit\Media;

/**
 * What a collection accepts and where its objects live: the disk, a key prefix under the app's own, the types (MIME => extension) and the largest size.
 * The app declares one per collection and passes it to every upload operation; nothing here reads a request.
 */
final readonly class MediaRules
{
    /** @param  array<string, string>  $types  MIME type => stored extension */
    public function __construct(
        public string $disk,
        public string $prefix,
        public array $types,
        public int $maxBytes,
        public int $ttlSeconds = 600,
    ) {}

    public function accepts(string $mime): bool
    {
        return isset($this->types[$mime]);
    }

    /** A content-addressed key, so the same bytes in a scope share one object. */
    public function key(string $sha256, string $mime): string
    {
        return trim($this->prefix, '/').'/'.substr($sha256, 0, 2).'/'.$sha256.'.'.$this->types[$mime];
    }
}
