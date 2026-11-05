<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add school ownership and audit tracking fields.
     */
    public function up(): void
    {
        $defaultSchoolId = DB::table('schools')->where('slug', 'smk-ppn-1-sukaraja')->value('id')
            ?? DB::table('schools')->value('id');

        // 1. Add school_id to school_settings
        Schema::table('school_settings', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->cascadeOnDelete();
        });

        if ($defaultSchoolId) {
            DB::table('school_settings')->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);
        }

        // Do not discard existing rows or guess their ownership to force one row per school.
        // A nullable unique key still allows schools without settings while preventing duplicates.
        Schema::table('school_settings', function (Blueprint $table) {
            $table->unique('school_id');
        });

        // 2. Add school_id, target, diff, and correlation_id to audit_logs
        Schema::table('audit_logs', function (Blueprint $table) use ($defaultSchoolId) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
            $table->string('target_type')->nullable()->after('action');
            $table->string('target_id')->nullable()->after('target_type');
            $table->jsonb('diff')->nullable()->after('details');
            $table->uuid('correlation_id')->nullable()->after('diff');

            $table->index(['school_id', 'created_at']);
            $table->index('correlation_id');
        });

        if ($defaultSchoolId) {
            DB::table('audit_logs')->whereNull('school_id')->update(['school_id' => $defaultSchoolId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['correlation_id']);
            $table->dropIndex(['school_id', 'created_at']);

            $table->dropForeign(['school_id']);
            $table->dropColumn(['correlation_id', 'diff', 'target_id', 'target_type', 'school_id']);
        });

        Schema::table('school_settings', function (Blueprint $table) {
            $table->dropUnique(['school_id']);
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });
    }
};
