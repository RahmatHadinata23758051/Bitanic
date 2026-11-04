<?php

namespace App\Console\Commands;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\DeviceApiKey;
use App\Models\Media;
use App\Models\PaymentHistory;
use App\Models\ScheduleParticipant;
use App\Models\SensorThreshold;
use App\Models\SubscriptionPlan;
use App\Models\TelemetryReading;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserSubscription;
use App\Services\LegacyDataParser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportLegacyDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bitanic:import-legacy
                            {--path= : Path to dump_upiedge.sql}
                            {--fresh : Freshly migrate database before import}
                            {--dev-passwords : Set all imported user passwords to "password" for local dev testing}
                            {--force : Force execution in production environment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical users, profiles, devices, subscriptions, schedules, media, and telemetry from dump_upiedge.sql';

    /**
     * Execute the console command.
     */
    public function handle(LegacyDataParser $parser): int
    {
        $this->info('Starting Bitanic Legacy Data ETL Migration...');

        if (app()->environment('production')) {
            if ($this->option('fresh')) {
                $this->error('Operasi --fresh DILARANG KERAS pada environment production demi integritas dan keamanan data!');
                return Command::FAILURE;
            }

            if (!$this->option('force')) {
                $this->error('Aplikasi berjalan pada environment production. Operasi import legacy memerlukan flag --force.');
                return Command::FAILURE;
            }
        }

        $customPath = $this->option('path');
        $filePath = $customPath
            ? (str_starts_with($customPath, '/') || str_contains($customPath, ':\\') ? $customPath : base_path($customPath))
            : dirname(base_path()) . DIRECTORY_SEPARATOR . 'dump_upiedge.sql';

        if (!file_exists($filePath)) {
            $this->error("Legacy SQL dump file not found at: {$filePath}");
            return Command::FAILURE;
        }

        if ($this->option('fresh') && !app()->environment('production')) {
            $this->warn('Running php artisan migrate:fresh before import...');
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $this->line("Reading SQL dump from: <comment>{$filePath}</comment>");
        $sql = file_get_contents($filePath);

        DB::beginTransaction();

        try {
            $userCounts = $this->importUsersAndProfiles($parser, $sql);
            $planCounts = $this->importSubscriptionPlans($parser, $sql);
            $subCounts = $this->importUserSubscriptions($parser, $sql);
            $payCounts = $this->importPaymentHistories($parser, $sql);
            $deviceCounts = $this->importDevicesAndKeys($parser, $sql);
            $threshCounts = $this->importSensorThresholds($parser, $sql);
            $schedCounts = $this->importActivitySchedules($parser, $sql);
            $mediaCounts = $this->importMedia($parser, $sql);
            $telemetryCounts = $this->importTelemetryReadings($parser, $sql);

            DB::commit();

            $this->newLine();
            $this->info(' Legacy ETL Migration Completed Successfully!');
            $this->table(
                ['Entity / Domain', 'Imported Count'],
                [
                    ['Users (Superuser / Guru / Siswa)', $userCounts['users']],
                    ['User Profiles (Guru & Siswa)', $userCounts['profiles']],
                    ['Subscription Plans', $planCounts],
                    ['User Subscriptions', $subCounts],
                    ['Payment Histories', $payCounts],
                    ['IoT Devices & Keys', $deviceCounts],
                    ['Sensor Thresholds', $threshCounts],
                    ['Activity Schedules & Participants', "{$schedCounts['schedules']} schedules ({$schedCounts['participants']} participants)"],
                    ['Media Tanam / Plot Media', $mediaCounts],
                    ['Telemetry Readings (Unified)', $telemetryCounts],
                ]
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("ETL Migration Failed: {$e->getMessage()}");
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    protected function importUsersAndProfiles(LegacyDataParser $parser, string $sql): array
    {
        $this->line('Importing users and profiles...');
        $userRows = $parser->parseInsertRows($sql, 'users');
        $studentRows = $parser->parseInsertRows($sql, 'students');
        $lecturerRows = $parser->parseInsertRows($sql, 'lecturers');

        $usersCount = 0;
        foreach ($userRows as $row) {
            // (id, name, email, email_verified_at, password, role, remember_token, created_at, updated_at)
            $id = $row[0];
            $name = $row[1];
            $email = $row[2];
            $verifiedAt = $row[3] ? Carbon::parse($row[3]) : null;
            $password = $row[4];
            if ($this->option('dev-passwords') || app()->environment('testing')) {
                if (app()->environment('production')) {
                    $this->warn('Opsi --dev-passwords diabaikan pada environment production demi keamanan kredensial.');
                } else {
                    $password = \Illuminate\Support\Facades\Hash::make('password');
                }
            }
            $rawRole = $row[5] ?? 'siswa';
            $role = match ($rawRole) {
                'dosen' => 'guru',
                'mahasiswa' => 'siswa',
                default => $rawRole,
            };
            $rememberToken = $row[6] ?? null;
            $createdAt = $row[7] ? Carbon::parse($row[7]) : now();
            $updatedAt = $row[8] ? Carbon::parse($row[8]) : now();

            $user = User::find($id);
            if (! $user) {
                $user = new User();
                $user->id = $id;
            }
            $user->fill([
                'name' => $name,
                'email' => $email,
                'email_verified_at' => $verifiedAt,
                'role' => $role,
                'remember_token' => $rememberToken,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);
            $user->setRawAttributes(array_merge($user->getAttributes(), ['password' => $password]));
            $user->save();
            $usersCount++;
        }

        $profilesCount = 0;
        // Import Siswa profiles (dari data legacy students)
        foreach ($studentRows as $row) {
            // (id, nim, name, user_id, email, department, semester, address, photo, gender, created_at, updated_at)
            $userId = $row[3];
            $nim = $row[1];
            $department = $row[5];
            $semester = $row[6];
            $address = $row[7];
            $photo = $row[8];
            $gender = $row[9];

            if ($userId && User::find($userId)) {
                UserProfile::updateOrCreate(
                    ['user_id' => $userId],
                    [
                        'type' => 'siswa',
                        'identifier_number' => $nim,
                        'department' => $department,
                        'semester' => (string) $semester,
                        'address' => $address,
                        'photo' => $photo,
                        'gender' => $gender,
                    ]
                );
                $profilesCount++;
            }
        }

        // Import Guru profiles (dari data legacy lecturers)
        foreach ($lecturerRows as $row) {
            // (id, nip, name, user_id, email, department, address, photo, gender, created_at, updated_at)
            $userId = $row[3];
            $nip = $row[1];
            $department = $row[5];
            $address = $row[6];
            $photo = $row[7];
            $gender = $row[8];

            if ($userId && User::find($userId)) {
                UserProfile::updateOrCreate(
                    ['user_id' => $userId],
                    [
                        'type' => 'guru',
                        'identifier_number' => $nip,
                        'department' => $department,
                        'semester' => null,
                        'address' => $address,
                        'photo' => $photo,
                        'gender' => $gender,
                    ]
                );
                $profilesCount++;
            }
        }

        return ['users' => $usersCount, 'profiles' => $profilesCount];
    }

    protected function importSubscriptionPlans(LegacyDataParser $parser, string $sql): int
    {
        $this->line('Importing subscription plans...');
        $rows = $parser->parseInsertRows($sql, 'subscription_plans');
        $count = 0;

        foreach ($rows as $row) {
            // (id, name, quota, duration_days, price, created_at, updated_at)
            $id = $row[0];
            $name = $row[1];
            $quota = $row[2] ?? 0;
            $duration = $row[3] ?? 30;
            $price = $row[4] ?? 0;
            $createdAt = $row[5] ? Carbon::parse($row[5]) : now();
            $updatedAt = $row[6] ? Carbon::parse($row[6]) : now();

            SubscriptionPlan::updateOrCreate(
                ['id' => $id],
                [
                    'name' => $name,
                    'code' => Str::slug($name),
                    'description' => "Paket langganan agritech {$name}",
                    'price' => $price,
                    'duration_days' => $duration ?: 30,
                    'features' => ['ai_quota' => $quota],
                    'is_active' => true,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function importUserSubscriptions(LegacyDataParser $parser, string $sql): int
    {
        $this->line('Importing user subscriptions...');
        $rows = $parser->parseInsertRows($sql, 'user_subscriptions');
        $count = 0;

        foreach ($rows as $row) {
            // (id, user_id, subscription_plan_id, ?, ends_at, created_at, updated_at)
            $id = $row[0];
            $userId = $row[1];
            $planId = $row[2];
            $endsAt = $row[4] ? Carbon::parse($row[4]) : now()->addMonth();
            $createdAt = $row[5] ? Carbon::parse($row[5]) : now();
            $updatedAt = $row[6] ? Carbon::parse($row[6]) : now();

            if (User::find($userId) && SubscriptionPlan::find($planId)) {
                UserSubscription::updateOrCreate(
                    ['id' => $id],
                    [
                        'user_id' => $userId,
                        'plan_id' => $planId,
                        'starts_at' => $createdAt,
                        'ends_at' => $endsAt,
                        'status' => $endsAt->isFuture() ? 'active' : 'expired',
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    protected function importPaymentHistories(LegacyDataParser $parser, string $sql): int
    {
        $this->line('Importing payment histories...');
        $rows = $parser->parseInsertRows($sql, 'payment_histories');
        $count = 0;

        foreach ($rows as $row) {
            // (id, user_id, subscription_plan_id, order_id, payment_type, status, response, created_at, updated_at)
            $id = $row[0];
            $userId = $row[1];
            $planId = $row[2];
            $orderId = $row[3];
            $paymentType = $row[4];
            $status = $row[5] ?? 'pending';
            $response = $row[6] ? json_decode($row[6], true) : null;
            $createdAt = $row[7] ? Carbon::parse($row[7]) : now();
            $updatedAt = $row[8] ? Carbon::parse($row[8]) : now();

            $plan = SubscriptionPlan::find($planId);
            $amount = $plan ? $plan->price : 0;

            if (User::find($userId)) {
                PaymentHistory::updateOrCreate(
                    ['order_id' => $orderId],
                    [
                        'user_id' => $userId,
                        'plan_id' => $planId,
                        'amount' => $amount,
                        'status' => $status,
                        'payment_type' => $paymentType,
                        'transaction_time' => $createdAt,
                        'raw_response' => $response,
                        'created_at' => $createdAt,
                        'updated_at' => $updatedAt,
                    ]
                );
                $count++;
            }
        }

        return $count;
    }

    protected function importDevicesAndKeys(LegacyDataParser $parser, string $sql): int
    {
        $this->line('Importing IoT devices and generating default API keys...');

        // Pastikan perangkat utama BTLP-UPI terdaftar
        $device = Device::firstOrCreate(
            ['code' => 'BTLP-UPI'],
            [
                'name' => 'BTLP UPI Station 1',
                'connection_type' => 'wifi',
                'connection_config' => ['ssid' => 'UPI-Hotspot'],
                'installation_date' => Carbon::parse('2025-08-01'),
                'note' => 'Perangkat stasiun sensor tanah Modbus RS485 UPI Bandung',
                'is_active' => true,
                'last_seen_at' => null,
            ]
        );

        // Siapkan API Key terenkripsi SHA-256 untuk BTLP-UPI
        $rawKey = 'bitanic-secret-key-btlp-upi-2026';
        $keyHash = hash('sha256', $rawKey);

        DeviceApiKey::firstOrCreate(
            ['key_hash' => $keyHash],
            [
                'device_id' => $device->id,
                'key_prefix' => substr($rawKey, 0, 12),
                'revoked_at' => null,
            ]
        );

        return Device::count();
    }

    protected function importSensorThresholds(LegacyDataParser $parser, string $sql): int
    {
        $this->line('Importing sensor thresholds...');
        $rows = $parser->parseInsertRows($sql, 'sensor_thresholds');
        $device = Device::where('code', 'BTLP-UPI')->first();

        foreach ($rows as $row) {
            // (id, parameter, min_value, max_value, created_at, updated_at)
            $parameter = $row[1];
            $min = $row[2];
            $max = $row[3];

            SensorThreshold::updateOrCreate(
                ['device_id' => $device?->id, 'parameter' => $parameter],
                ['min_value' => $min, 'max_value' => $max]
            );
        }

        // Standar default batas aman agritech jika parameter lain belum didefinisikan
        $defaultThresholds = [
            'Nitrogen' => ['min' => 20, 'max' => 150],
            'Phosphorus' => ['min' => 20, 'max' => 100],
            'Potassium' => ['min' => 50, 'max' => 200],
            'Ec' => ['min' => 100, 'max' => 1500],
            'Ph' => ['min' => 5.5, 'max' => 7.5],
            'Temperature' => ['min' => 20, 'max' => 32],
            'Humidity' => ['min' => 60, 'max' => 90],
        ];

        foreach ($defaultThresholds as $param => $bounds) {
            SensorThreshold::firstOrCreate(
                ['device_id' => $device?->id, 'parameter' => $param],
                ['min_value' => $bounds['min'], 'max_value' => $bounds['max']]
            );
        }

        return SensorThreshold::count();
    }

    protected function importActivitySchedules(LegacyDataParser $parser, string $sql): array
    {
        $this->line('Importing activity schedules and participants...');
        $scheduleRows = $parser->parseInsertRows($sql, 'activity_schedules');
        $lecturerRows = $parser->parseInsertRows($sql, 'activity_schedules_lecturers');
        $studentRows = $parser->parseInsertRows($sql, 'activity_schedules_students');
        $legacyStudents = $parser->parseInsertRows($sql, 'students');
        $legacyLecturers = $parser->parseInsertRows($sql, 'lecturers');
        $device = Device::where('code', 'BTLP-UPI')->first();

        // Build mapping from legacy student/lecturer ID to user_id
        $studentUserMap = [];
        foreach ($legacyStudents as $st) {
            // (id, nim, name, user_id, ...)
            $studentUserMap[$st[0]] = $st[3];
        }

        $lecturerUserMap = [];
        foreach ($legacyLecturers as $lc) {
            // (id, nip, name, user_id, ...)
            $lecturerUserMap[$lc[0]] = $lc[3];
        }

        $scheduleIdMap = [];
        $schedCount = 0;
        foreach ($scheduleRows as $row) {
            // (id, day, activity_date, start_time, end_time, agenda, created_at, updated_at)
            $legacyId = $row[0];
            $date = $row[2];
            $start = $row[3];
            $end = $row[4];
            $agenda = $row[5];
            $createdAt = $row[6] ? Carbon::parse($row[6]) : now();
            $updatedAt = $row[7] ? Carbon::parse($row[7]) : now();

            $schedule = ActivitySchedule::updateOrCreate(
                ['activity_date' => $date, 'start_time' => $start, 'end_time' => $end],
                [
                    'device_id' => $device?->id,
                    'title' => 'Praktikum Lapangan Pertanian',
                    'agenda' => $agenda,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]
            );

            $scheduleIdMap[$legacyId] = $schedule->id;
            $schedCount++;
        }

        $partCount = 0;
        // Guru participants
        foreach ($lecturerRows as $row) {
            // (id, activity_schedule_id, lecturer_id, created_at, updated_at)
            $legacySchedId = $row[1];
            $legacyLecturerId = $row[2];

            $schedId = $scheduleIdMap[$legacySchedId] ?? null;
            $userId = $lecturerUserMap[$legacyLecturerId] ?? User::where('role', 'guru')->value('id');

            if ($schedId && $userId) {
                ScheduleParticipant::updateOrCreate(
                    ['activity_schedule_id' => $schedId, 'user_id' => $userId],
                    ['role' => 'guru']
                );
                $partCount++;
            }
        }

        // Siswa participants
        foreach ($studentRows as $row) {
            // (id, activity_schedule_id, student_id, created_at, updated_at)
            $legacySchedId = $row[1];
            $legacyStudentId = $row[2];

            $schedId = $scheduleIdMap[$legacySchedId] ?? null;
            $userId = $studentUserMap[$legacyStudentId] ?? User::where('role', 'siswa')->value('id');

            if ($schedId && $userId) {
                ScheduleParticipant::updateOrCreate(
                    ['activity_schedule_id' => $schedId, 'user_id' => $userId],
                    ['role' => 'siswa']
                );
                $partCount++;
            }
        }

        return ['schedules' => $schedCount, 'participants' => $partCount];
    }

    protected function importMedia(LegacyDataParser $parser, string $sql): int
    {
        $this->line('Importing media tanam records...');
        $rows = $parser->parseInsertRows($sql, 'media');
        $device = Device::where('code', 'BTLP-UPI')->first();
        $count = 0;

        foreach ($rows as $row) {
            // (id, name, plants, plants_condition, created_at, updated_at)
            $id = $row[0];
            $name = $row[1];
            $plants = $row[2] ? json_decode($row[2], true) : null;
            $conditions = $row[3] ? json_decode($row[3], true) : null;
            $createdAt = $row[4] ? Carbon::parse($row[4]) : now();
            $updatedAt = $row[5] ? Carbon::parse($row[5]) : now();

            Media::updateOrCreate(
                ['id' => $id],
                [
                    'name' => $name,
                    'device_id' => $device?->id,
                    'plants' => $plants,
                    'plants_condition' => $conditions,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ]
            );
            $count++;
        }

        return $count;
    }

    protected function importTelemetryReadings(LegacyDataParser $parser, string $sql): int
    {
        $this->line('Importing historical sensor readings into telemetry_readings...');
        $rows = $parser->parseInsertRows($sql, 'filtered_fix_stations');
        if (empty($rows)) {
            $rows = $parser->parseInsertRows($sql, 'fix_stations');
        }

        $device = Device::where('code', 'BTLP-UPI')->first();
        if (!$device) {
            return 0;
        }

        $chunkSize = 100;
        $totalInserted = 0;
        $batch = [];

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            // (id, device_id, samples, created_at, updated_at)
            $samplesRaw = $row[2];
            $createdAt = $row[3] ? Carbon::parse($row[3]) : now();

            $samples = is_string($samplesRaw) ? json_decode($samplesRaw, true) : $samplesRaw;
            if (!is_array($samples)) {
                $bar->advance();
                continue;
            }

            $nitrogen = (float) ($samples['Nitrogen'] ?? 0);
            $phosphorus = (float) ($samples['Phosporus'] ?? 0);
            $potassium = (float) ($samples['Kalium'] ?? 0);
            $ec = (float) ($samples['Ec'] ?? 0);
            $ph = (float) ($samples['Ph'] ?? 0);
            $temperature = (float) ($samples['Temperature'] ?? 0);
            $humidity = (float) ($samples['Humidity'] ?? 0);

            $batch[] = [
                'device_id' => $device->id,
                'provenance' => 'fixture',
                'nitrogen' => $nitrogen,
                'phosphorus' => $phosphorus,
                'potassium' => $potassium,
                'ec' => $ec,
                'ph' => $ph,
                'temperature' => $temperature,
                'humidity' => $humidity,
                'raw_payload' => json_encode($samples),
                'threshold_flags' => json_encode([]),
                'recorded_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($batch) >= $chunkSize) {
                TelemetryReading::insert($batch);
                $totalInserted += count($batch);
                $batch = [];
            }

            $bar->advance();
        }

        if (!empty($batch)) {
            TelemetryReading::insert($batch);
            $totalInserted += count($batch);
        }

        $bar->finish();
        $this->newLine();

        return $totalInserted;
    }
}
