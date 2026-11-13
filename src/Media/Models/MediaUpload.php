<?php

namespace Tey\Kit\Media\Models;

use Illuminate\Database\Eloquent\Model;
use Tey\Kit\Media\Concerns\IsMediaUpload;
use Tey\Kit\Media\Contracts\MediaUpload as MediaUploadContract;

/**
 * The kit's upload model: the default for `kit.models.media_upload`. Its table is already the app's (`kit.media.uploads_table`), so an app needs its
 * own model only to add behaviour. Deprecated since 0.19 for new code: reach it through `Tey\Kit\Media\MediaUploads::model()`. The behaviour is
 * Concerns\IsMediaUpload.
 *
 * @property string $id
 * @property string $scope
 * @property ?string $mutation_id
 * @property string $disk
 * @property string $key
 * @property string $mime
 * @property int $bytes
 * @property string $sha256
 * @property string $status
 * @property ?int $width
 * @property ?int $height
 * @property ?string $multipart_upload_id
 */
class MediaUpload extends Model implements MediaUploadContract
{
    use IsMediaUpload;
}
