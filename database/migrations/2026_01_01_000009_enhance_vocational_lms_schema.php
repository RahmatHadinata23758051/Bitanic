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
        // 1. Tambah atribut workstation meja praktik pada tabel devices
        Schema::table('devices', function (Blueprint $table) {
            $table->string('bench_location')->nullable()->after('name');
            $table->string('ip_address', 45)->nullable()->after('bench_location');
            $table->string('mac_address', 30)->nullable()->after('ip_address');
            $table->string('firmware_version', 50)->nullable()->after('mac_address');
            $table->string('protocol', 50)->default('Modbus RS485')->after('firmware_version');
            $table->string('status', 30)->default('online')->after('protocol'); // online, offline, calibrating, anomaly
            $table->smallInteger('battery_percent')->default(100)->after('status');
            $table->smallInteger('rssi_signal')->default(-55)->after('battery_percent');
            $table->jsonb('relays_state')->nullable()->after('rssi_signal');
            $table->jsonb('calibration_offsets')->nullable()->after('relays_state');
        });

        // 2. Tambah atribut master data siswa & guru pada tabel user_profiles
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->string('class_grade', 50)->nullable()->after('department'); // e.g. "XII ATPH 1"
            $table->string('assigned_kit_id', 50)->nullable()->after('class_grade'); // e.g. "TK-01"
            $table->string('group_name', 100)->nullable()->after('assigned_kit_id'); // e.g. "Kelompok 1"
            $table->string('specialization')->nullable()->after('group_name'); // e.g. "Agroteknologi & Pertanian Presisi IoT"
            $table->string('phone', 30)->nullable()->after('specialization');
            $table->smallInteger('attendance_percent')->default(100)->after('phone');
            $table->decimal('average_grade', 5, 2)->default(0.00)->after('attendance_percent');
            $table->char('competency_predicative', 1)->default('B')->after('average_grade'); // A, B, C, D
            $table->boolean('trainer_certified')->default(false)->after('competency_predicative');
        });

        // 3. Buat tabel raw_data_logs untuk Modbus RS485 Hex Debugger
        Schema::create('raw_data_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('kit_code', 50)->nullable()->index();
            $table->text('packet_hex');
            $table->string('protocol', 50)->default('Modbus RS485 RTU');
            $table->string('level', 20)->default('INFO')->index(); // DEBUG, INFO, WARN, ERROR
            $table->jsonb('parsed_payload')->nullable();
            $table->timestampTz('recorded_at')->useCurrent()->index();
            $table->timestamps();
        });

        // 4. Buat tabel audit_logs untuk jejak audit operasional laboratorium
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('actor_role', 50)->default('guru');
            $table->string('action'); // e.g. "Aktuasi Relai Hardware", "Kalibrasi Sensor Offset"
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        // 5. Buat tabel school_settings untuk konfigurasi sekolah & ambang batas sensor
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->string('school_name')->default('SMK Pertanian Pembangunan Negeri 1 Sukaraja');
            $table->string('academic_year', 20)->default('2026/2027');
            $table->string('semester', 20)->default('Ganjil');
            $table->string('course_name')->default('Agribisnis Tanaman Pangan & Hortikultura (ATPH)');
            $table->string('lead_teacher')->default('Ir. Ahmad Suryanto, S.P., M.Pd.');
            $table->jsonb('sensor_thresholds')->nullable();
            $table->boolean('audio_alarm_enabled')->default(true);
            $table->integer('auto_sync_interval_sec')->default(3);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('raw_data_logs');

        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'class_grade',
                'assigned_kit_id',
                'group_name',
                'specialization',
                'phone',
                'attendance_percent',
                'average_grade',
                'competency_predicative',
                'trainer_certified',
            ]);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn([
                'bench_location',
                'ip_address',
                'mac_address',
                'firmware_version',
                'protocol',
                'status',
                'battery_percent',
                'rssi_signal',
                'relays_state',
                'calibration_offsets',
            ]);
        });
    }
};
