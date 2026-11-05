<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A finalization's frozen printed fields: the values a flow's snapshot() declares from records the subject does not own (a customer's name and address), so
// the finalized subject prints what was certified, and a hash per key, so a later edit is named as drift. Null for flows that declare no snapshot.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kit_finalizations', function (Blueprint $t): void {
            $t->json('snapshot')->nullable();
            $t->json('snapshot_hashes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kit_finalizations', function (Blueprint $t): void {
            $t->dropColumn(['snapshot', 'snapshot_hashes']);
        });
    }
};
