<?php

namespace Tey\Kit\Media;

/**
 * A multipart store that can copy an object server-side only when the target key does not exist yet (S3 `CopyObject` with `If-None-Match: *`).
 * CompleteMultipartUpload uses it to move a verified staging object onto its content key without ever overwriting what is there. A store without
 * it gets HEAD-then-copy through the disk instead, which can't rule out a write landing between the two.
 */
interface ConditionalCopyStore
{
    /** Copies `$from` to `$to` unless `$to` exists; true when it copied, false when `$to` was already there. */
    public function copyIfAbsent(string $from, string $to, int $bytes): bool;
}
