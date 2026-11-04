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
        Schema::create('sensor_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->onDelete('cascade');
            $table->string('parameter')->index(); // Nitrogen, Phosporus, Kalium, Ec, Ph, Temperature, Humidity
            $table->decimal('min_value', 8, 2)->default(0);
            $table->decimal('max_value', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['device_id', 'parameter']);
        });

        Schema::create('telemetry_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->decimal('nitrogen', 8, 2)->default(0);
            $table->decimal('phosphorus', 8, 2)->default(0);
            $table->decimal('potassium', 8, 2)->default(0);
            $table->decimal('ec', 8, 2)->default(0);
            $table->decimal('ph', 4, 2)->default(0);
            $table->decimal('temperature', 5, 2)->default(0);
            $table->decimal('humidity', 5, 2)->default(0);
            $table->jsonb('raw_payload');
            $table->jsonb('threshold_flags')->nullable();
            $table->timestamp('recorded_at')->useCurrent()->index();
            $table->timestamps();

            // Composite Index untuk query telemetri berkecepatan tinggi
            $table->index(['device_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemetry_readings');
        Schema::dropIfExists('sensor_thresholds');
    }
};
