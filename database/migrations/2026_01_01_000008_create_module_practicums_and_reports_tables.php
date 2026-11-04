<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('module_practicums', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('meeting_number')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->unsignedInteger('duration_minutes')->default(180);
            $table->string('badge')->default('Hardware K3');
            $table->text('capaian_pembelajaran')->nullable();
            $table->jsonb('tujuan_pembelajaran')->default('[]');
            $table->jsonb('alat_bahan')->default('[]');
            $table->text('theory_summary')->nullable();
            $table->jsonb('wiring_pinout')->default('[]');
            $table->jsonb('steps')->default('[]');
            $table->jsonb('rubric')->default('[]');
            $table->jsonb('lks_questions')->default('[]');
            $table->timestamps();
        });

        Schema::create('student_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('module_id')->constrained('module_practicums')->onDelete('cascade');
            $table->foreignId('activity_schedule_id')->nullable()->constrained('activity_schedules')->onDelete('set null');
            $table->jsonb('lks_answers')->default('[]');
            $table->text('observation_notes')->nullable();
            $table->jsonb('telemetry_snapshot')->nullable();
            $table->enum('status', ['draft', 'submitted', 'graded', 'revision'])->default('draft')->index();
            $table->jsonb('rubric_scores')->nullable();
            $table->decimal('final_grade', 5, 2)->nullable();
            $table->text('teacher_feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['user_id', 'module_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_reports');
        Schema::dropIfExists('module_practicums');
    }
};
