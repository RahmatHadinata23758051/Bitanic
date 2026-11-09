<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\ModulePracticum;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SetupBrowserE2eFixturesCommand extends Command
{
    protected $signature = 'bitanic:setup-browser-e2e';

    protected $description = 'Set up fixtures for real browser E2E interaction tests';

    public function handle(): int
    {
        $this->info('Configuring browser E2E test database fixtures...');

        $schoolA = School::firstOrCreate(
            ['id' => 1],
            [
                'npsn' => '20201001',
                'name' => 'SMK Pertanian Pembangunan Negeri 1 Sukaraja',
                'slug' => 'smk-pertanian-negeri-1',
                'status' => 'active',
            ]
        );

        $schoolB = School::firstOrCreate(
            ['id' => 2],
            [
                'npsn' => '20201002',
                'name' => 'SMK Pertanian Swasta 2 Berdikari',
                'slug' => 'smk-pertanian-swasta-2',
                'status' => 'active',
            ]
        );

        SchoolSetting::firstOrCreate(
            ['school_id' => 1],
            [
                'school_name' => 'SMK Pertanian Pembangunan Negeri 1 Sukaraja',
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'course_name' => 'Agribisnis Tanaman Pangan & Hortikultura (ATPH)',
                'lead_teacher' => 'Ir. Ahmad Suryanto, S.P., M.Pd.',
                'audio_alarm_enabled' => true,
                'auto_sync_interval_sec' => 3,
            ]
        );

        $password = Hash::make('Password123!');

        // 1. Superuser
        $su = User::updateOrCreate(
            ['email' => 'superuser@bitanic.id'],
            [
                'name' => 'Super Administrator Bitanic',
                'role' => 'superuser',
                'school_id' => 1,
                'password' => $password,
                'email_verified_at' => now(),
                'must_change_password' => false,
            ]
        );
        UserProfile::updateOrCreate(
            ['user_id' => $su->id],
            ['type' => 'guru', 'identifier_number' => 'SU-001', 'department' => 'IT Admin']
        );

        // 2. Admin
        $adm = User::updateOrCreate(
            ['email' => 'admin@smk.id'],
            [
                'name' => 'M. Fikri Nurhakim, S.Kom.',
                'role' => 'admin',
                'school_id' => 1,
                'password' => $password,
                'email_verified_at' => now(),
                'must_change_password' => false,
            ]
        );
        UserProfile::updateOrCreate(
            ['user_id' => $adm->id],
            ['type' => 'guru', 'identifier_number' => 'ADM-001', 'department' => 'Teknisi Lab']
        );

        // 3. Guru
        $guru = User::updateOrCreate(
            ['email' => 'guru@smk.id'],
            [
                'name' => 'Ir. Ahmad Suryanto, S.P., M.Pd.',
                'role' => 'guru',
                'school_id' => 1,
                'password' => $password,
                'email_verified_at' => now(),
                'must_change_password' => false,
            ]
        );
        UserProfile::updateOrCreate(
            ['user_id' => $guru->id],
            ['type' => 'guru', 'identifier_number' => 'GUR-001', 'department' => 'ATPH']
        );

        // 4. Siswa
        $siswa = User::updateOrCreate(
            ['email' => 'siswa@smk.id'],
            [
                'name' => 'Bagus Pratama Dwi Putra',
                'role' => 'siswa',
                'school_id' => 1,
                'password' => $password,
                'email_verified_at' => now(),
                'must_change_password' => false,
            ]
        );
        UserProfile::updateOrCreate(
            ['user_id' => $siswa->id],
            ['type' => 'siswa', 'identifier_number' => '0091122334', 'class_grade' => 'XII ATPH 1', 'assigned_kit_id' => 'TK-01']
        );

        // 5. Hardware Fleet
        $deviceA = Device::firstOrCreate(
            ['code' => 'TK-01'],
            [
                'school_id' => 1,
                'name' => 'BITANIC Precision Agritech Kit #01',
                'bench_location' => 'Meja Praktik 01',
                'is_active' => true,
                'status' => 'online',
            ]
        );

        Device::firstOrCreate(
            ['code' => 'TK-02'],
            [
                'school_id' => 2,
                'name' => 'BITANIC Kit Sekolah B #02',
                'bench_location' => 'Meja Praktik B',
                'is_active' => true,
                'status' => 'online',
            ]
        );

        // 6. Practicum Module
        ModulePracticum::firstOrCreate(
            ['meeting_number' => 1],
            [
                'title' => 'Pengenalan Sensor 7-in-1 Modbus RS485',
                'badge' => 'Hardware & K3',
                'duration_minutes' => 120,
                'rubric' => [
                    ['id' => 'aspek_k3', 'name' => 'K3 dan SOP', 'weight' => 50],
                    ['id' => 'aspek_wiring', 'name' => 'Pengawatan RS485', 'weight' => 50],
                ],
            ]
        );

        // 7. Telemetry Reading
        TelemetryReading::create([
            'device_id' => $deviceA->id,
            'ph' => 6.5,
            'humidity' => 60.0,
            'ec' => 1200,
            'temperature' => 26.5,
            'nitrogen' => 65,
            'phosphorus' => 35,
            'potassium' => 140,
            'provenance' => 'hardware',
            'recorded_at' => now(),
        ]);

        $this->info('Browser E2E fixtures configured successfully!');

        return self::SUCCESS;
    }
}
