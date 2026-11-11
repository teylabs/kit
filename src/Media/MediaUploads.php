<?php

namespace Tey\Kit\Media;

use Illuminate\Database\Schema\Blueprint;

/**
 * The pending-upload ledger's table, which the app creates in its own migration: `Schema::create(MediaUploads::table(), MediaUploads::schema(...))`.
 */
final class MediaUploads
{
    public static function table(): string
    {
        return (string) config('kit.media.uploads_table', 'kit_media_uploads');
    }

    public static function schema(Blueprint $table): void
    {
        $table->ulid('id')->primary();
        $table->string('scope');
        $table->string('mutation_id')->nullable();
        $table->string('disk');
        $table->string('key');
        $table->string('mime');
        $table->unsignedBigInteger('bytes');
        $table->char('sha256', 64);
        $table->string('status')->default('pending');
        $table->unsignedInteger('width')->nullable();
        $table->unsignedInteger('height')->nullable();
        self::addMultipart($table);
        $table->timestamps();
        $table->unique(['scope', 'mutation_id']);
        $table->index(['scope', 'sha256', 'status']);
    }

    /**
     * The S3 multipart upload id, for an existing table in the app's own additive migration: `Schema::table(MediaUploads::table(), MediaUploads::addMultipart(...))`.
     * `schema()` includes it. Without it, multipart stays off and every upload is a single PUT.
     */
    public static function addMultipart(Blueprint $table): void
    {
        $table->string('multipart_upload_id')->nullable();
    }
}
