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
        // 1. Master Tabel Kelas (Classrooms)
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('name', 100)->index(); // e.g. "X ATPH 1", "XI ATPH 2"
            $table->string('department')->default('Agribisnis Tanaman Pangan & Hortikultura (ATPH)');
            $table->string('academic_year', 20)->default('2026/2027');
            $table->foreignId('homeroom_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Master Tabel Kelompok Praktikum (Student Groups)
        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete(); // Default Trainer Kit Meja Praktik
            $table->string('name', 100); // e.g. "Kelompok 1 (Bedengan A)"
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. Tambah relational foreign key pada user_profiles (tetap pertahankan string column untuk kompatibilitas)
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->foreignId('classroom_id')->nullable()->after('class_grade')->constrained('classrooms')->nullOnDelete();
            $table->foreignId('student_group_id')->nullable()->after('group_name')->constrained('student_groups')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropForeign(['student_group_id']);
            $table->dropForeign(['classroom_id']);
            $table->dropColumn(['student_group_id', 'classroom_id']);
        });

        Schema::dropIfExists('student_groups');
        Schema::dropIfExists('classrooms');
    }
};
