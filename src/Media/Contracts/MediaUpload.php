<?php

namespace Tey\Kit\Media\Contracts;

/** One declared upload: the model `kit.models.media_upload` names, which uses Concerns\IsMediaUpload. Its table stays `kit.media.uploads_table`. */
interface MediaUpload
{
    public function ready(): bool;
}
