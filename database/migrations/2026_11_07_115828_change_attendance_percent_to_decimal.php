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
        Schema::table('user_profiles', function (Blueprint $table) {
            // Change attendance_percent from smallInteger to decimal(5,2) to support fractional percentages
            // PostgreSQL requires explicit cast: USING attendance_percent::decimal(5,2)
            $table->decimal('attendance_percent', 5, 2)->default(100.00)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Revert to smallInteger, rounding values to nearest integer
            $table->smallInteger('attendance_percent')->default(100)->change();
        });
    }
};
