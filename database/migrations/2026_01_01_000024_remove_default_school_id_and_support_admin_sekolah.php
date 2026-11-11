<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * In accordance with DP-1, DP-2, and DP-3:
     * 1. Remove database-level default on users.school_id to prevent silent tenant assignment.
     * 2. Add composite index on users(school_id, role) for optimal tenant querying.
     */
    public function up(): void
    {
        // 1. Remove database-level column default on users.school_id in PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN school_id DROP DEFAULT;');
        }

        // 2. Add composite index on (school_id, role)
        Schema::table('users', function (Blueprint $table) {
            $table->index(['school_id', 'role'], 'idx_users_school_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_school_role');
        });

        // In accordance with explicit tenant provisioning contract (DP-3),
        // we DO NOT restore DEFAULT 1 on users.school_id to prevent silent tenant association.
    }
};
