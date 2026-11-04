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
        Schema::create('activity_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->onDelete('set null');
            $table->string('title')->default('Praktikum');
            $table->date('activity_date')->index();
            $table->time('start_time');
            $table->time('end_time');
            $table->text('agenda')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_schedule_id')->constrained('activity_schedules')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('role', ['lecturer', 'student'])->default('student');
            $table->timestamps();

            $table->unique(['activity_schedule_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_participants');
        Schema::dropIfExists('activity_schedules');
    }
};
