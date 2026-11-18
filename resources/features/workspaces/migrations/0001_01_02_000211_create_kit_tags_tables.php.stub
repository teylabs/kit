<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Flags and tags (spatie/laravel-tags, on the kit's own tables). `kit_tags`: a flag (type `flag`) has no workspace, one vocabulary for all; a person's
// tag belongs to its workspace. `kit_taggables`: a tag on a record, with the record's workspace and, for a flag, its reason and link (`meta`).
// `kit_flag_dismissals`: a flag someone dismissed on a record ("Not a duplicate"), so it is not raised again. Each table keyed by `workspace_id` goes
// with its workspace; with RLS on, protect them (Rls::protect) or list them in kit.tenancy.rls.except.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_tags', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->nullable()->constrained('workspaces');
            $t->json('name');
            $t->json('slug');
            $t->string('type')->nullable()->index();
            $t->integer('order_column')->nullable();
            $t->timestamps();
        });
        Schema::create('kit_taggables', function (Blueprint $t): void {
            $t->foreignId('tag_id')->constrained('kit_tags')->cascadeOnDelete();
            $t->morphs('taggable');
            $t->foreignId('workspace_id')->nullable()->constrained('workspaces');
            $t->json('meta')->nullable();
            $t->unique(['tag_id', 'taggable_id', 'taggable_type']);
        });
        Schema::create('kit_flag_dismissals', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->nullable()->constrained('workspaces');
            $t->morphs('flaggable');
            $t->string('flag');
            $t->unsignedBigInteger('dismissed_by')->nullable();
            $t->timestamp('dismissed_at');
            $t->unique(['flaggable_type', 'flaggable_id', 'flag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_flag_dismissals');
        Schema::dropIfExists('kit_taggables');
        Schema::dropIfExists('kit_tags');
    }
};
