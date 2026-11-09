<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\RawDataLog;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class VocationalLmsSeeder extends Seeder
{
    /**
     * Seed vocational LMS master data: School settings, Trainer Kits, Teachers, Students, Raw logs, Audit logs.
     */
    public function run(): void
    {
        // 1. School Settings
        SchoolSetting::updateOrCreate(
            ['id' => 1],
            [
                'school_id' => 1,
                'school_name' => 'SMK Pertanian Pembangunan Negeri 1 Sukaraja',
                'academic_year' => '2026/2027',
                'semester' => 'Ganjil',
                'course_name' => 'Agribisnis Tanaman Pangan & Hortikultura (ATPH)',
                'lead_teacher' => 'Ir. Ahmad Suryanto, S.P., M.Pd.',
                'sensor_thresholds' => [
                    'minMoisture' => 30.0,
                    'maxMoisture' => 75.0,
                    'minPH' => 5.5,
                    'maxPH' => 7.2,
                    'minEC' => 800,
                    'maxEC' => 2200,
                    'minNitrogen' => 40.0,
                    'maxNitrogen' => 120.0,
                ],
                'audio_alarm_enabled' => true,
                'auto_sync_interval_sec' => 3,
            ]
        );

        // 2. Hardware Fleet: 6 Trainer Kits
        $kits = [
            [
                'code' => 'TK-01',
                'name' => 'BITANIC Precision Agritech Kit #01',
                'bench_location' => 'Meja Praktik 01 - Bedengan Tomat Ceri',
                'ip_address' => '192.168.10.101',
                'mac_address' => '24:0A:C4:58:1A:F0',
                'firmware_version' => 'v2.4.1-PROD-RS485',
                'protocol' => 'Modbus RS485',
                'status' => 'online',
                'battery_percent' => 94,
                'rssi_signal' => -54,
                'connection_type' => 'wifi',
                'is_active' => true,
                'relays_state' => [
                    'irrigationPump' => false,
                    'fertigationValve' => false,
                    'mistingFan' => true,
                    'growLight' => true,
                ],
                'calibration_offsets' => [
                    'phOffset' => 0.05,
                    'moistureOffset' => -1.2,
                    'ecOffset' => 12,
                ],
            ],
            [
                'code' => 'TK-02',
                'name' => 'BITANIC Precision Agritech Kit #02',
                'bench_location' => 'Meja Praktik 02 - Polybag Cabai Rawit',
                'ip_address' => '192.168.10.102',
                'mac_address' => '24:0A:C4:58:1A:F2',
                'firmware_version' => 'v2.4.1-PROD-RS485',
                'protocol' => 'Modbus RS485',
                'status' => 'anomaly',
                'battery_percent' => 88,
                'rssi_signal' => -62,
                'connection_type' => 'wifi',
                'is_active' => true,
                'relays_state' => [
                    'irrigationPump' => true,
                    'fertigationValve' => false,
                    'mistingFan' => false,
                    'growLight' => true,
                ],
                'calibration_offsets' => [
                    'phOffset' => -0.1,
                    'moistureOffset' => 0.5,
                    'ecOffset' => -15,
                ],
            ],
            [
                'code' => 'TK-03',
                'name' => 'BITANIC Precision Agritech Kit #03',
                'bench_location' => 'Meja Praktik 03 - Hidroponik Substrat Melon',
                'ip_address' => '192.168.10.103',
                'mac_address' => '24:0A:C4:58:1B:11',
                'firmware_version' => 'v2.4.1-PROD-RS485',
                'protocol' => 'MQTT WiFi',
                'status' => 'online',
                'battery_percent' => 91,
                'rssi_signal' => -58,
                'connection_type' => 'wifi',
                'is_active' => true,
                'relays_state' => [
                    'irrigationPump' => false,
                    'fertigationValve' => true,
                    'mistingFan' => true,
                    'growLight' => false,
                ],
                'calibration_offsets' => [
                    'phOffset' => 0.0,
                    'moistureOffset' => 0.0,
                    'ecOffset' => 0,
                ],
            ],
            [
                'code' => 'TK-04',
                'name' => 'BITANIC Precision Agritech Kit #04',
                'bench_location' => 'Meja Praktik 04 - Media Gambut & Cocopeat',
                'ip_address' => '192.168.10.104',
                'mac_address' => '24:0A:C4:58:1B:45',
                'firmware_version' => 'v2.4.1-PROD-RS485',
                'protocol' => 'Modbus RS485',
                'status' => 'online',
                'battery_percent' => 76,
                'rssi_signal' => -65,
                'connection_type' => 'wifi',
                'is_active' => true,
                'relays_state' => [
                    'irrigationPump' => false,
                    'fertigationValve' => false,
                    'mistingFan' => false,
                    'growLight' => true,
                ],
                'calibration_offsets' => [
                    'phOffset' => 0.02,
                    'moistureOffset' => 0.3,
                    'ecOffset' => -5,
                ],
            ],
            [
                'code' => 'TK-05',
                'name' => 'BITANIC Precision Agritech Kit #05',
                'bench_location' => 'Meja Praktik 05 - Pembibitan Selada Romaine',
                'ip_address' => '192.168.10.105',
                'mac_address' => '24:0A:C4:58:1C:02',
                'firmware_version' => 'v2.3.9-TEST',
                'protocol' => 'Modbus RS485',
                'status' => 'calibrating',
                'battery_percent' => 82,
                'rssi_signal' => -60,
                'connection_type' => 'wifi',
                'is_active' => true,
                'relays_state' => [
                    'irrigationPump' => false,
                    'fertigationValve' => false,
                    'mistingFan' => true,
                    'growLight' => true,
                ],
                'calibration_offsets' => [
                    'phOffset' => -0.15,
                    'moistureOffset' => 1.1,
                    'ecOffset' => 25,
                ],
            ],
            [
                'code' => 'TK-06',
                'name' => 'BITANIC Precision Agritech Kit #06',
                'bench_location' => 'Meja Praktik 06 - Aeroponik Mini Tanaman Hias',
                'ip_address' => '192.168.10.106',
                'mac_address' => '24:0A:C4:58:1D:90',
                'firmware_version' => 'v2.4.1-PROD-RS485',
                'protocol' => 'MQTT WiFi',
                'status' => 'offline',
                'battery_percent' => 12,
                'rssi_signal' => -88,
                'connection_type' => 'wifi',
                'is_active' => true,
                'relays_state' => [
                    'irrigationPump' => false,
                    'fertigationValve' => false,
                    'mistingFan' => false,
                    'growLight' => false,
                ],
                'calibration_offsets' => [
                    'phOffset' => 0.0,
                    'moistureOffset' => 0.0,
                    'ecOffset' => 0,
                ],
            ],
        ];

        $deviceModels = [];
        foreach ($kits as $kitData) {
            $kitData['school_id'] = 1;
            $device = Device::updateOrCreate(['code' => $kitData['code']], $kitData);
            $deviceModels[$device->code] = $device;
        }

        // 3. User Seeders (Guru, Siswa, Admin)
        $passwordHash = Hash::make('password123');

        // A. Guru
        $teachers = [
            [
                'name' => 'Ir. Ahmad Suryanto, S.P., M.Pd.',
                'email' => 'guru@smk.id',
                'alias_email' => 'ahmad.suryanto@guru.smk.belajar.id',
                'role' => 'guru',
                'nip' => '19780512 200501 1 004',
                'specialization' => 'Agroteknologi & Pertanian Presisi IoT',
                'phone' => '0812-3456-7890',
                'classes' => 'XII ATPH 1, XII ATPH 2',
                'certified' => true,
            ],
            [
                'name' => 'Siti Rohani, S.P., M.Si.',
                'email' => 'siti.rohani@guru.smk.belajar.id',
                'alias_email' => null,
                'role' => 'guru',
                'nip' => '19840315 200902 2 003',
                'specialization' => 'Ilmu Kesuburan Tanah & Pemupukan',
                'phone' => '0813-9876-5432',
                'classes' => 'XII ATPH 1, XII ATPH 2',
                'certified' => true,
            ],
            [
                'name' => 'Budi Santoso, S.ST.',
                'email' => 'budi.santoso@guru.smk.belajar.id',
                'alias_email' => null,
                'role' => 'guru',
                'nip' => '19920820 201803 1 002',
                'specialization' => 'Mekanisasi Pertanian & Irigasi Cerdas',
                'phone' => '0857-1122-3344',
                'classes' => 'XI ATPH 1, XI ATPH 2',
                'certified' => false,
            ],
        ];

        foreach ($teachers as $t) {
            $user = User::updateOrCreate(
                ['email' => $t['email']],
                [
                    'school_id' => 1,
                    'name' => $t['name'],
                    'password' => $passwordHash,
                    'role' => 'guru',
                    'email_verified_at' => now(),
                    'must_change_password' => false,
                ]
            );
            UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'type' => 'guru',
                    'identifier_number' => $t['nip'],
                    'department' => 'Agribisnis Tanaman Pangan & Hortikultura',
                    'specialization' => $t['specialization'],
                    'class_grade' => $t['classes'],
                    'phone' => $t['phone'],
                    'trainer_certified' => $t['certified'],
                ]
            );

            if ($t['alias_email']) {
                $alias = User::updateOrCreate(
                    ['email' => $t['alias_email']],
                    [
                        'school_id' => 1,
                        'name' => $t['name'],
                        'password' => $passwordHash,
                        'role' => 'guru',
                        'email_verified_at' => now(),
                        'must_change_password' => false,
                    ]
                );
                UserProfile::updateOrCreate(
                    ['user_id' => $alias->id],
                    [
                        'type' => 'guru',
                        'identifier_number' => $t['nip'],
                        'department' => 'Agribisnis Tanaman Pangan & Hortikultura',
                        'specialization' => $t['specialization'],
                        'class_grade' => $t['classes'],
                        'phone' => $t['phone'],
                        'trainer_certified' => $t['certified'],
                    ]
                );
            }
        }

        // B. Siswa
        $students = [
            [
                'name' => 'Bagus Pratama Dwi Putra',
                'email' => 'siswa@smk.id',
                'alias_email' => 'bagus.pratama@siswa.smk.belajar.id',
                'nisn' => '0068192841',
                'class' => 'XII ATPH 1',
                'group' => 'Kelompok 1 (Bedengan A)',
                'kit_id' => 'TK-01',
                'attendance' => 100,
                'average_grade' => 93.5,
                'predicative' => 'A',
                'gender' => 'L',
            ],
            [
                'name' => 'Siti Nurhaliza',
                'email' => 'siti.nurhaliza@siswa.smk.belajar.id',
                'alias_email' => null,
                'nisn' => '0068192842',
                'class' => 'XII ATPH 1',
                'group' => 'Kelompok 1 (Bedengan A)',
                'kit_id' => 'TK-01',
                'attendance' => 95,
                'average_grade' => 88.0,
                'predicative' => 'A',
                'gender' => 'P',
            ],
            [
                'name' => 'Dewi Anggraini',
                'email' => 'dewi.anggraini@siswa.smk.belajar.id',
                'alias_email' => null,
                'nisn' => '0068192843',
                'class' => 'XII ATPH 1',
                'group' => 'Kelompok 2 (Polybag B)',
                'kit_id' => 'TK-02',
                'attendance' => 90,
                'average_grade' => 78.5,
                'predicative' => 'B',
                'gender' => 'P',
            ],
            [
                'name' => 'Rizky Fauzan',
                'email' => 'rizky.fauzan@siswa.smk.belajar.id',
                'alias_email' => null,
                'nisn' => '0068192844',
                'class' => 'XII ATPH 1',
                'group' => 'Kelompok 2 (Polybag B)',
                'kit_id' => 'TK-02',
                'attendance' => 85,
                'average_grade' => 74.0,
                'predicative' => 'C',
                'gender' => 'L',
            ],
            [
                'name' => 'Fajar Ramadhan',
                'email' => 'fajar.ramadhan@siswa.smk.belajar.id',
                'alias_email' => null,
                'nisn' => '0068192845',
                'class' => 'XII ATPH 2',
                'group' => 'Kelompok 3 (Hidroponik)',
                'kit_id' => 'TK-03',
                'attendance' => 100,
                'average_grade' => 91.0,
                'predicative' => 'A',
                'gender' => 'L',
            ],
            [
                'name' => 'Anisa Rahma',
                'email' => 'anisa.rahma@siswa.smk.belajar.id',
                'alias_email' => null,
                'nisn' => '0068192846',
                'class' => 'XII ATPH 2',
                'group' => 'Kelompok 3 (Hidroponik)',
                'kit_id' => 'TK-03',
                'attendance' => 92,
                'average_grade' => 86.5,
                'predicative' => 'B',
                'gender' => 'P',
            ],
            [
                'name' => 'Hendra Wijaya',
                'email' => 'hendra.wijaya@siswa.smk.belajar.id',
                'alias_email' => null,
                'nisn' => '0068192847',
                'class' => 'XII ATPH 2',
                'group' => 'Kelompok 4 (Media Gambut)',
                'kit_id' => 'TK-04',
                'attendance' => 88,
                'average_grade' => 79.0,
                'predicative' => 'B',
                'gender' => 'L',
            ],
            [
                'name' => 'Maya Safitri',
                'email' => 'maya.safitri@siswa.smk.belajar.id',
                'alias_email' => null,
                'nisn' => '0068192848',
                'class' => 'XII ATPH 2',
                'group' => 'Kelompok 4 (Media Gambut)',
                'kit_id' => 'TK-04',
                'attendance' => 96,
                'average_grade' => 89.0,
                'predicative' => 'A',
                'gender' => 'P',
            ],
        ];

        foreach ($students as $s) {
            $user = User::updateOrCreate(
                ['email' => $s['email']],
                [
                    'school_id' => 1,
                    'name' => $s['name'],
                    'password' => $passwordHash,
                    'role' => 'siswa',
                    'email_verified_at' => now(),
                    'must_change_password' => false,
                ]
            );
            UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'type' => 'siswa',
                    'identifier_number' => $s['nisn'],
                    'class_grade' => $s['class'],
                    'group_name' => $s['group'],
                    'assigned_kit_id' => $s['kit_id'],
                    'attendance_percent' => $s['attendance'],
                    'average_grade' => $s['average_grade'],
                    'competency_predicative' => $s['predicative'],
                    'gender' => $s['gender'],
                ]
            );

            if ($s['alias_email']) {
                $alias = User::updateOrCreate(
                    ['email' => $s['alias_email']],
                    [
                        'school_id' => 1,
                        'name' => $s['name'],
                        'password' => $passwordHash,
                        'role' => 'siswa',
                        'email_verified_at' => now(),
                        'must_change_password' => false,
                    ]
                );
                UserProfile::updateOrCreate(
                    ['user_id' => $alias->id],
                    [
                        'type' => 'siswa',
                        'identifier_number' => $s['nisn'],
                        'class_grade' => $s['class'],
                        'group_name' => $s['group'],
                        'assigned_kit_id' => $s['kit_id'],
                        'attendance_percent' => $s['attendance'],
                        'average_grade' => $s['average_grade'],
                        'competency_predicative' => $s['predicative'],
                        'gender' => $s['gender'],
                    ]
                );
            }
        }

        // C. Admin Lab & Superuser
        $admin = User::updateOrCreate(
            ['email' => 'admin@smk.id'],
            [
                'school_id' => 1,
                'name' => 'M. Fikri Nurhakim, S.Kom.',
                'password' => $passwordHash,
                'role' => 'admin',
                'email_verified_at' => now(),
                'must_change_password' => false,
            ]
        );
        UserProfile::updateOrCreate(
            ['user_id' => $admin->id],
            [
                'type' => 'guru',
                'identifier_number' => 'TEK-IOT-2024-001',
                'department' => 'Teknisi Laboratorium IoT Agronomi',
                'specialization' => 'Embedded Systems & RS485 Automation',
                'phone' => '0821-9988-7766',
                'trainer_certified' => true,
            ]
        );

        $superuser = User::updateOrCreate(
            ['email' => 'superuser@bitanic.id'],
            [
                'school_id' => 1,
                'name' => 'Super Administrator Bitanic',
                'password' => $passwordHash,
                'role' => 'superuser',
                'email_verified_at' => now(),
                'must_change_password' => false,
            ]
        );
        UserProfile::updateOrCreate(
            ['user_id' => $superuser->id],
            [
                'type' => 'guru',
                'identifier_number' => 'SUPER-001',
                'department' => 'Administrator Sistem & Cloud IoT',
                'specialization' => 'Superuser Administrator',
                'phone' => '0812-0000-0001',
                'trainer_certified' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'superuser@smk.id'],
            [
                'school_id' => 1,
                'name' => 'Super Administrator SMK',
                'password' => $passwordHash,
                'role' => 'superuser',
                'email_verified_at' => now(),
                'must_change_password' => false,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@bitanic.id'],
            [
                'school_id' => 1,
                'name' => 'Administrator Bitanic',
                'password' => $passwordHash,
                'role' => 'admin',
                'email_verified_at' => now(),
                'must_change_password' => false,
            ]
        );

        // 4. Raw Data Logs (Modbus RS485 Hex Stream)
        $tk1 = $deviceModels['TK-01'] ?? null;
        $tk2 = $deviceModels['TK-02'] ?? null;

        $rawLogs = [
            [
                'device_id' => $tk1?->id,
                'kit_code' => 'TK-01',
                'packet_hex' => '01 03 0E 01 A9 01 0C 05 8C 02 85 00 44 00 22 00 91 A4 F1',
                'protocol' => 'Modbus RS485 RTU',
                'level' => 'INFO',
                'parsed_payload' => [
                    'soilMoisture' => 42.5,
                    'soilTemp' => 26.8,
                    'soilEC' => 1420,
                    'soilPH' => 6.45,
                    'nitrogen' => 68,
                    'phosphorus' => 34,
                    'potassium' => 145,
                ],
                'recorded_at' => now()->subSeconds(3),
            ],
            [
                'device_id' => $tk1?->id,
                'kit_code' => 'TK-01',
                'packet_hex' => '01 05 00 00 FF 00 8C 3A',
                'protocol' => 'Modbus RS485 RTU',
                'level' => 'INFO',
                'parsed_payload' => [
                    'command' => 'WRITE_SINGLE_COIL',
                    'coil' => 0,
                    'state' => 'ON',
                    'description' => 'Aktuasi Pompa Irigasi Tetes',
                ],
                'recorded_at' => now()->subSeconds(15),
            ],
            [
                'device_id' => $tk2?->id,
                'kit_code' => 'TK-02',
                'packet_hex' => '02 03 0E 00 B6 01 1D 03 7A 01 E5 00 18 00 12 00 5C B2 11',
                'protocol' => 'Modbus RS485 RTU',
                'level' => 'WARN',
                'parsed_payload' => [
                    'soilMoisture' => 18.2,
                    'soilTemp' => 28.5,
                    'soilEC' => 890,
                    'soilPH' => 4.85,
                    'nitrogen' => 24,
                    'warning' => 'ANOMALY_PH_ACID_DETECTED',
                ],
                'recorded_at' => now()->subSeconds(25),
            ],
            [
                'device_id' => $tk2?->id,
                'kit_code' => 'TK-02',
                'packet_hex' => '02 83 02 61 30',
                'protocol' => 'Modbus RS485 RTU',
                'level' => 'ERROR',
                'parsed_payload' => [
                    'exception_code' => '0x02',
                    'meaning' => 'ILLEGAL_DATA_ADDRESS',
                    'description' => 'Percobaan pembacaan register di luar jangkauan probe.',
                ],
                'recorded_at' => now()->subMinutes(2),
            ],
            [
                'device_id' => $tk1?->id,
                'kit_code' => 'TK-01',
                'packet_hex' => '01 03 00 00 00 07 04 08',
                'protocol' => 'Modbus RS485 RTU',
                'level' => 'DEBUG',
                'parsed_payload' => [
                    'type' => 'POLL_REQUEST',
                    'slave_id' => 1,
                    'function_code' => 3,
                    'start_reg' => '0x0000',
                    'num_reg' => 7,
                ],
                'recorded_at' => now()->subMinutes(5),
            ],
        ];

        foreach ($rawLogs as $log) {
            RawDataLog::create($log);
        }

        // 5. Audit Logs
        $auditLogs = [
            [
                'user_id' => $admin->id,
                'actor_name' => 'M. Fikri Nurhakim, S.Kom.',
                'actor_role' => 'admin',
                'action' => 'Inisialisasi Sistem Lab',
                'details' => 'Mengonfigurasi 6 Trainer Kit IoT untuk semester ganjil 2026/2027.',
                'ip_address' => '192.168.10.1',
                'created_at' => now()->subDays(2),
            ],
            [
                'user_id' => User::where('email', 'guru@smk.id')->first()?->id,
                'actor_name' => 'Ir. Ahmad Suryanto, S.P., M.Pd.',
                'actor_role' => 'guru',
                'action' => 'Plotting Jadwal Praktikum',
                'details' => 'Menjadwalkan Modul 01 untuk kelas XII ATPH 1 pada Lab Smart Agriculture 1.',
                'ip_address' => '192.168.10.15',
                'created_at' => now()->subDay(),
            ],
            [
                'user_id' => User::where('email', 'guru@smk.id')->first()?->id,
                'actor_name' => 'Ir. Ahmad Suryanto, S.P., M.Pd.',
                'actor_role' => 'guru',
                'action' => 'Aktuasi Relai Hardware',
                'details' => 'Menyalakan Kipas Sirkulasi Udara & LED Fotosintesis pada TK-01.',
                'ip_address' => '192.168.10.15',
                'created_at' => now()->subHours(4),
            ],
            [
                'user_id' => User::where('email', 'guru@smk.id')->first()?->id,
                'actor_name' => 'Ir. Ahmad Suryanto, S.P., M.Pd.',
                'actor_role' => 'guru',
                'action' => 'Penilaian Rubrik LKS',
                'details' => 'Menilai Laporan Modul 01 siswa Bagus Pratama (Nilai: 95.0, Sangat Kompeten).',
                'ip_address' => '192.168.10.15',
                'created_at' => now()->subHours(2),
            ],
            [
                'user_id' => User::where('email', 'siswa@smk.id')->first()?->id,
                'actor_name' => 'Bagus Pratama Dwi Putra',
                'actor_role' => 'siswa',
                'action' => 'Pengumpulan Laporan Praktikum',
                'details' => 'Mengumpulkan LKS Modul 02 dengan auto snapshot telemetri sensor TK-01.',
                'ip_address' => '192.168.10.101',
                'created_at' => now()->subMinutes(45),
            ],
        ];

        foreach ($auditLogs as $audit) {
            $audit['school_id'] = 1;
            AuditLog::create($audit);
        }
    }
}
