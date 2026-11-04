<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prevent new readings from receiving an assumed hardware provenance.
     */
    public function up(): void
    {
        DB::table('telemetry_readings')
            ->whereNull('provenance')
            ->update(['provenance' => 'fixture']);

        Schema::table('telemetry_readings', function (Blueprint $table) {
            $table->string('provenance')->default('fixture')->change();
        });
    }

    /**
     * Restore the legacy default for rollback compatibility.
     */
    public function down(): void
    {
        Schema::table('telemetry_readings', function (Blueprint $table) {
            $table->string('provenance')->default('hardware')->change();
        });
    }
};
