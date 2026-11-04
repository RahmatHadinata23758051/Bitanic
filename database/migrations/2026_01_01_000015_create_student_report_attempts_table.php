<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the immutable student_report_attempts table for append-only attempt history.
     * Each submission appends a new row; prior attempts remain unchanged.
     * BR-203: Migrate LKS to Session-Scoped Evidence and Immutable Attempts.
     */
    public function up(): void
    {
        Schema::create('student_report_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_report_id')->constrained('student_reports')->onDelete('cascade');
            $table->unsignedInteger('attempt_number');
            $table->jsonb('lks_answers');
            $table->text('observation_notes')->nullable();
            $table->jsonb('telemetry_snapshot')->nullable();
            $table->foreignId('evidence_reading_id')->nullable()->constrained('telemetry_readings')->onDelete('set null');
            $table->foreignId('device_id')->nullable()->constrained('devices')->onDelete('set null');
            $table->foreignId('activity_schedule_id')->nullable()->constrained('activity_schedules')->onDelete('set null');
            $table->timestamp('submitted_at');
            $table->timestamps();

            // Each report can have only one of each attempt number
            $table->unique(['student_report_id', 'attempt_number']);
            $table->index(['student_report_id', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_report_attempts');
    }
};
