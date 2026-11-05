<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The finalize gate's records. A subject is named by its flow's alias and its key as a string, so a composite key (a tenant and a local number, say) fits;
// every row also carries the workspace, and every read is scoped to it.
return new class extends Migration
{
    public function up(): void
    {
        // Who confirmed a step, when, and the fingerprint of what they confirmed. A row is never updated: confirming again adds one, and a confirmation counts
        // only while its fingerprint equals the step's current one.
        Schema::create('kit_finalize_confirmations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->string('flow', 64);
            $t->string('subject_key', 191);
            $t->string('step', 64);
            $t->string('fingerprint', 64);
            $t->unsignedBigInteger('confirmed_by');
            $t->string('channel', 16);
            $t->timestamp('confirmed_at');
            $t->index(['workspace_id', 'flow', 'subject_key', 'step', 'id']);
        });
        // One row per finalization: who certified what (statement version and text hash, the gate's revision and a hash of the certified state) through which
        // channel, and, when it was undone, who undid it, when and why. A row is never deleted, so the history reads finalized, un-finalized, finalized.
        Schema::create('kit_finalizations', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->string('flow', 64);
            $t->string('subject_key', 191);
            $t->unsignedBigInteger('finalized_by');
            $t->timestamp('finalized_at');
            $t->string('channel', 16);
            $t->string('statement_version', 64);
            $t->string('statement_hash', 64);
            $t->string('revision', 64);
            $t->string('snapshot_hash', 64);
            $t->unsignedBigInteger('unfinalized_by')->nullable();
            $t->timestamp('unfinalized_at')->nullable();
            $t->string('unfinalized_channel', 16)->nullable();
            $t->text('unfinalize_reason')->nullable();
            $t->index(['workspace_id', 'flow', 'subject_key', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_finalizations');
        Schema::dropIfExists('kit_finalize_confirmations');
    }
};
