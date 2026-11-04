<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create versioned, auditable sensor calibration profiles.
     */
    public function up(): void
    {
        Schema::create('sensor_calibration_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('sensor_model', 100);
            $table->string('firmware_version', 50)->nullable();
            $table->string('profile_name', 150);
            $table->unsignedInteger('version');
            $table->string('parameter', 50);
            $table->string('method', 50);
            $table->jsonb('parameters');
            $table->string('unit', 50);
            $table->string('register_address', 20);
            $table->decimal('min_range', 12, 4);
            $table->decimal('max_range', 12, 4);
            $table->timestampTz('effective_from');
            $table->timestampTz('effective_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestampsTz();

            $table->unique(['device_id', 'parameter', 'version'], 'sensor_calibration_profile_version_unique');
            $table->index(['device_id', 'parameter', 'effective_from'], 'sensor_calibration_profile_effective_index');
            $table->index(['sensor_model', 'firmware_version']);
        });

        Schema::table('telemetry_readings', function (Blueprint $table) {
            $table->jsonb('raw_metrics')->nullable()->after('raw_payload');
            $table->foreignId('calibration_profile_id')->nullable()->after('applied_offsets')
                ->constrained('sensor_calibration_profiles')->nullOnDelete();
            $table->unsignedInteger('calibration_version')->nullable()->after('calibration_profile_id');
            $table->timestampTz('calibration_applied_at')->nullable()->after('calibration_version');
        });
    }

    /**
     * Roll back calibration schema additions.
     */
    public function down(): void
    {
        Schema::table('telemetry_readings', function (Blueprint $table) {
            $table->dropForeign(['calibration_profile_id']);
            $table->dropColumn([
                'raw_metrics',
                'calibration_profile_id',
                'calibration_version',
                'calibration_applied_at',
            ]);
        });

        Schema::dropIfExists('sensor_calibration_profiles');
    }
};
