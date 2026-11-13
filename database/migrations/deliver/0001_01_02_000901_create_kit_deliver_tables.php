<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The deliver feature's records. A delivery is one email (To and Cc, the internal copy as Bcc) of one subject, sent now or at `send_at`; rows are never
// deleted. A subject is named by its delivery definition and its key as a string, as finalize names it; every row carries the workspace.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->string('definition', 64);
            $t->string('subject_key', 191);
            // scheduled → sending → sent | failed | unknown; scheduled → cancelled. `unknown` (a send that may or may not have left) is never retried.
            $t->string('state', 16);
            $t->boolean('test')->default(false);
            $t->string('claim_key', 120);
            $t->unsignedBigInteger('created_by');
            $t->string('channel', 16);
            $t->timestamp('created_at');
            $t->timestamp('send_at')->nullable();
            $t->timestamp('claimed_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->string('subject_line', 255);
            $t->text('message');
            $t->string('version', 191)->nullable();
            $t->unsignedBigInteger('finalization_id')->nullable();
            $t->string('attachment', 16)->default('none');
            $t->string('provider_message_id', 255)->nullable();
            $t->text('error')->nullable();
            $t->unsignedBigInteger('resent_from_id')->nullable();
            $t->unsignedBigInteger('cancelled_by')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->string('cancel_reason', 255)->nullable();
            // The customer link: a sha256 of its token, never the token; revoking or expiry turns the link into a 404.
            $t->string('token_hash', 64)->nullable();
            $t->timestamp('token_expires_at')->nullable();
            $t->timestamp('token_revoked_at')->nullable();
            $t->timestamp('first_opened_at')->nullable();
            $t->unique(['workspace_id', 'claim_key']);
            $t->index(['workspace_id', 'definition', 'subject_key', 'id']);
            $t->index(['state', 'send_at']);
            $t->index('provider_message_id');
        });
        Schema::create('kit_delivery_recipients', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->foreignId('delivery_id')->constrained('kit_deliveries');
            $t->string('role', 8);
            $t->string('address', 255);
            $t->string('name', 255)->nullable();
            // pending → sent → delivered | bounced | complained, from the provider's webhooks.
            $t->string('status', 16)->default('pending');
            $t->timestamp('last_event_at')->nullable();
            $t->unique(['delivery_id', 'address']);
        });
        // Each provider event once: a webhook delivered twice is ignored by this key.
        Schema::create('kit_delivery_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained();
            $t->foreignId('recipient_id')->constrained('kit_delivery_recipients');
            $t->string('event', 16);
            $t->string('provider_event_id', 191);
            $t->timestamp('occurred_at');
            $t->unique(['recipient_id', 'event', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_delivery_events');
        Schema::dropIfExists('kit_delivery_recipients');
        Schema::dropIfExists('kit_deliveries');
    }
};
