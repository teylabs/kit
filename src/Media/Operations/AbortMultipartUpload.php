<?php

namespace Tey\Kit\Media\Operations;

use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Tey\Kit\Media\Models\MediaUpload;
use Tey\Kit\Media\MultipartUploads;
use Throwable;

/**
 * Aborts a pending upload's S3 multipart upload, so the bucket drops its parts, and clears the id, so a replay of the mutation starts afresh. The
 * record stays pending for the app's pruner. An upload with no multipart id is left as it is. The client's abort route, a failed complete and
 * `kit:uploads:prune` all come here. Scope the record lookup to the caller's scope, as for confirm.
 */
final class AbortMultipartUpload
{
    use AsAction;

    public function handle(MediaUpload $upload): MediaUpload
    {
        $id = $upload->multipart_upload_id;
        if ($id === null || $upload->ready()) {
            return $upload;
        }
        $store = MultipartUploads::store($upload->disk) ?? throw new RuntimeException('media.upload.multipart_unavailable');
        try {
            $store->abort(MultipartUploads::stagingKey($upload->key, $upload->id), $id);
        } catch (Throwable $error) {
            // An upload started before staging keys (v0.15.85 and earlier) is open at the final key: abort it there, or rethrow the first error.
            try {
                $store->abort($upload->key, $id);
            } catch (Throwable) {
                throw $error;
            }
        }
        // Without touching updated_at, so an aborted row keeps its age and the pruner can still clear it in the same run.
        MediaUpload::query()->whereKey($upload->id)->where('multipart_upload_id', $id)->toBase()->update(['multipart_upload_id' => null]);

        return $upload->refresh();
    }
}
