<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tey\Kit\Features\Workspaces\Rls;

// A workspace's values of the settings registered with `Registry::setting(scope: 'workspace')`, one row per workspace and key. `value` is written through
// Laravel's `encrypted` cast (APP_KEY), so the column never holds the value in the clear. Protected by row-level security (Rls::ISOLATION); the RLS part is a
// no-op off PostgreSQL or with RLS off, and runs as the owner (the admin/migrator connection).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_secrets', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('key');
            $t->text('value');
            $t->timestamps();
            $t->unique(['workspace_id', 'key']);
        });
        Rls::protect('workspace_secrets', policy: Rls::ISOLATION);
    }

    public function down(): void
    {
        Rls::unprotect('workspace_secrets', policy: Rls::ISOLATION);
        Schema::dropIfExists('workspace_secrets');
    }
};
