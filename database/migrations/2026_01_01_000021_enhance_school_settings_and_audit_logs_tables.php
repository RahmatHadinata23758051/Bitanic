<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add school ownership and audit tracking fields.
     */
    public function up(): void
    {
        // 1. Add school_id to school_settings
        Schema::table('school_settings', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->cascadeOnDelete();
        });

        // Legacy settings do not carry reliable school ownership. Preserve unknown ownership
        // as NULL rather than assigning every row to one school; multiple NULL values remain
        // valid under the unique constraint while known school-owned rows remain distinct.
        Schema::table('school_settings', function (Blueprint $table) {
            $table->unique('school_id');
        });

        // 2. Add school_id, target, diff, and correlation_id to audit_logs
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->nullOnDelete();
            $table->string('target_type')->nullable()->after('action');
            $table->string('target_id')->nullable()->after('target_type');
            $table->jsonb('diff')->nullable()->after('details');
            $table->uuid('correlation_id')->nullable()->after('diff');

            $table->index(['school_id', 'created_at']);
            $table->index('correlation_id');
        });

        // Legacy audit records also have unknown school ownership. Leave it NULL rather than
        // falsely attributing historical actions to the default school.
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
