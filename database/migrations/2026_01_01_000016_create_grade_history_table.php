<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the immutable grade_history table for append-only assessment records.
     * BR-204: Assessment Revision and Grade History.
     */
    public function up(): void
    {
        Schema::create('grade_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_report_id')->constrained('student_reports')->onDelete('cascade');
            $table->foreignId('graded_by')->constrained('users')->onDelete('cascade');
            $table->jsonb('rubric_scores');
            $table->decimal('final_grade', 5, 2);
            $table->text('teacher_feedback')->nullable();
            $table->text('adjustment_reason')->nullable();
            $table->string('transition')->index(); // e.g. 'initial_grade', 'revision', 'adjustment'
            $table->timestamp('graded_at');
            $table->timestamps();

            $table->index(['student_report_id', 'graded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_history');
    }
};
