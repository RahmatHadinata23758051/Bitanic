<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to add evidence references, attempts, and telemetry provenance.
     */
    public function up(): void
    {
        Schema::table('telemetry_readings', function (Blueprint $table) {
            $table->string('provenance')->default('hardware')->index()->after('device_id');
            $table->jsonb('applied_offsets')->nullable()->after('raw_payload');
        });

        Schema::table('student_reports', function (Blueprint $table) {
            $table->foreignId('device_id')->nullable()->after('activity_schedule_id')->constrained('devices')->onDelete('set null');
            $table->foreignId('evidence_reading_id')->nullable()->after('device_id')->constrained('telemetry_readings')->onDelete('set null');
            $table->unsignedInteger('attempt_count')->default(1)->after('status');
            $table->text('revision_notes')->nullable()->after('teacher_feedback');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_reports', function (Blueprint $table) {
            $table->dropForeign(['device_id']);
            $table->dropForeign(['evidence_reading_id']);
            $table->dropColumn(['device_id', 'evidence_reading_id', 'attempt_count', 'revision_notes']);
        });

        Schema::table('telemetry_readings', function (Blueprint $table) {
            $table->dropIndex(['provenance']);
            $table->dropColumn(['provenance', 'applied_offsets']);
        });
    }
};
