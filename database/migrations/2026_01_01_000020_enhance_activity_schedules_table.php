<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enhance activity_schedules table with classroom, student group, and module references.
     */
    public function up(): void
    {
        Schema::table('activity_schedules', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('school_id')->constrained('classrooms')->nullOnDelete();
            $table->foreignId('student_group_id')->nullable()->after('classroom_id')->constrained('student_groups')->nullOnDelete();
            $table->foreignId('module_practicum_id')->nullable()->after('device_id')->constrained('module_practicums')->nullOnDelete();

            $table->index(['school_id', 'activity_date']);
            $table->index(['classroom_id', 'activity_date']);
            $table->index(['student_group_id', 'activity_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_schedules', function (Blueprint $table) {
            $table->dropIndex(['student_group_id', 'activity_date']);
            $table->dropIndex(['classroom_id', 'activity_date']);
            $table->dropIndex(['school_id', 'activity_date']);

            $table->dropForeign(['module_practicum_id']);
            $table->dropForeign(['student_group_id']);
            $table->dropForeign(['classroom_id']);

            $table->dropColumn(['module_practicum_id', 'student_group_id', 'classroom_id']);
        });
    }
};
