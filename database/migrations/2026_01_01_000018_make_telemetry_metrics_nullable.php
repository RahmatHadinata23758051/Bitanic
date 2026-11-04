<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to allow null values for unmeasured telemetry metrics.
     */
    public function up(): void
    {
        Schema::table('telemetry_readings', function (Blueprint $table) {
            $table->decimal('nitrogen', 8, 2)->nullable()->default(null)->change();
            $table->decimal('phosphorus', 8, 2)->nullable()->default(null)->change();
            $table->decimal('potassium', 8, 2)->nullable()->default(null)->change();
            $table->decimal('ec', 8, 2)->nullable()->default(null)->change();
            $table->decimal('ph', 4, 2)->nullable()->default(null)->change();
            $table->decimal('temperature', 5, 2)->nullable()->default(null)->change();
            $table->decimal('humidity', 5, 2)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telemetry_readings', function (Blueprint $table) {
            $table->decimal('nitrogen', 8, 2)->default(0)->change();
            $table->decimal('phosphorus', 8, 2)->default(0)->change();
            $table->decimal('potassium', 8, 2)->default(0)->change();
            $table->decimal('ec', 8, 2)->default(0)->change();
            $table->decimal('ph', 4, 2)->default(0)->change();
            $table->decimal('temperature', 5, 2)->default(0)->change();
            $table->decimal('humidity', 5, 2)->default(0)->change();
        });
    }
};
