<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What was final when a delivery was sent, for an app that finalizes through its own model: the delivery's finalizationRef() token. Null for
// deliveries without one, which keep `finalization_id`. An app may backfill it to correct rows sent before it existed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kit_deliveries', function (Blueprint $t): void {
            $t->string('finalization_ref', 191)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kit_deliveries', function (Blueprint $t): void {
            $t->dropColumn('finalization_ref');
        });
    }
};
