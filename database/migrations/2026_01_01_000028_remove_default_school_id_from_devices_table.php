<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * In accordance with Sprint 4 Pack A (DEVICE-04-01, DEVICE-04-02):
     * Remove database-level default on devices.school_id to prevent silent tenant assignment.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE devices ALTER COLUMN school_id DROP DEFAULT;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // In accordance with explicit tenant provisioning contract,
        // we DO NOT restore DEFAULT 1 on devices.school_id to prevent silent tenant association.
    }
};
