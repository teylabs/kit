<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 0.22: one row per signed-in session, the person's own (never a workspace's), listed on /me/security on every session driver. The session id is
        // encrypted, and found by its HMAC; the user agent is kept only as its browser and platform, the IP only as its /24 or /48 (SignedInDevices).
        // An app's own table of this name (one of its own devices, say) is never taken over: the kit refuses, naming it.
        if (Schema::hasTable('signed_in_devices')) {
            throw new RuntimeException('A signed_in_devices table already exists, so the kit will not create its own: it never takes over an app\'s table. Rename the app\'s table, then migrate again.');
        }
        Schema::create('signed_in_devices', function (Blueprint $t): void {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->char('session_hash', 64)->unique();
            $t->text('session_id');
            $t->string('browser', 40)->nullable();
            $t->string('platform', 40)->nullable();
            $t->string('ip_prefix', 45)->nullable();
            $t->char('country', 2)->nullable();
            $t->boolean('remembered')->default(false);
            $t->timestamp('created_at');
            $t->timestamp('last_seen_at');
            $t->index(['user_id', 'last_seen_at']);
            $t->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signed_in_devices');
    }
};
