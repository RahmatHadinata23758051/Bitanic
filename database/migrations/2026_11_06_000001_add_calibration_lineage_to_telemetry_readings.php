<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add per-parameter calibration lineage without rewriting historical readings.
     */
    public function up(): void
    {
        Schema::table('telemetry_readings', function (Blueprint $table) {
            $table->jsonb('calibration_lineage')->nullable()->after('applied_offsets');
        });
    }

    /**
     * Remove per-parameter calibration lineage.
     */
    public function down(): void
    {
        Schema::table('telemetry_readings', function (Blueprint $table) {
            $table->dropColumn('calibration_lineage');
        });
    }
};
