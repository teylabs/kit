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
        $table->timestamps();
        $table->unique(['scope', 'mutation_id']);
        $table->index(['scope', 'sha256', 'status']);
    }
}
