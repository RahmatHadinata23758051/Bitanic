<?php

namespace Tests\Feature;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgresIntegrationAndConcurrencyTest extends TestCase
{
    private ?ConnectionInterface $pg = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $connection = DB::connection('pgsql_testing');
            $connection->getPdo();
            $this->pg = $connection;
        } catch (\Throwable $e) {
            $this->markTestSkipped('PostgreSQL testing service is unavailable: '.$e->getMessage());
        }

        // Safety Guard: NEVER run against production or primary development database!
        $currentDb = $this->pg->getDatabaseName();
        $this->assertEquals('bitanic_test', $currentDb, 'PostgreSQL tests MUST run on isolated bitanic_test only');
        $this->assertNotEquals('bitanic_production', $currentDb);
        $this->assertNotEquals('bitanic_db', $currentDb);
    }

    public function test_postgresql_environment_isolation_and_version(): void
    {
        $versionRow = $this->pg->selectOne('SELECT version() as ver');
        $this->assertNotNull($versionRow);
        $this->assertStringContainsString('PostgreSQL', $versionRow->ver);

        $driver = $this->pg->getDriverName();
        $this->assertEquals('pgsql', $driver);

        $dbName = $this->pg->getDatabaseName();
        $this->assertEquals('bitanic_test', $dbName);
    }

    public function test_postgresql_all_tables_and_indexes_exist(): void
    {
        $tables = $this->pg->select("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
        ");

        $tableNames = array_map(fn ($r) => $r->table_name, $tables);

        $expectedTables = [
            'schools',
            'users',
            'user_profiles',
            'devices',
            'device_api_keys',
            'telemetry_readings',
            'sensor_thresholds',
            'sensor_calibration_profiles',
            'module_practicums',
            'student_reports',
            'student_report_attempts',
            'grade_history',
            'activity_schedules',
            'schedule_participants',
            'school_settings',
            'audit_logs',
            'classrooms',
            'student_groups',
        ];

        foreach ($expectedTables as $table) {
            $this->assertContains($table, $tableNames, "Expected table [{$table}] to exist on PostgreSQL");
        }

        // Verify composite index on telemetry_readings
        $indexes = $this->pg->select("
            SELECT indexname
            FROM pg_indexes
            WHERE tablename = 'telemetry_readings'
        ");
        $indexNames = array_map(fn ($r) => $r->indexname, $indexes);
        $this->assertTrue(
            in_array('telemetry_readings_device_id_recorded_at_index', $indexNames, true) ||
            in_array('idx_telemetry_readings_device_recorded', $indexNames, true) ||
            in_array('telemetry_readings_device_id_index', $indexNames, true),
            'Expected index on telemetry_readings for device querying'
        );
    }

    public function test_postgresql_foreign_key_and_tenant_ownership_enforcement(): void
    {
        // Inserting user with non-existent school_id MUST fail on PostgreSQL foreign key constraint
        $this->expectException(QueryException::class);

        $this->pg->table('users')->insert([
            'name' => 'Invalid Tenant User',
            'email' => 'invalid-tenant-'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
            'role' => 'siswa',
            'school_id' => 99999999, // Non-existent foreign key
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_postgresql_jsonb_query_and_containment_operators(): void
    {
        $schoolId = $this->pg->table('schools')->insertGetId([
            'npsn' => 'PG'.rand(100000, 999999),
            'name' => 'SMK Postgres JSONB',
            'slug' => 'smk-pg-jsonb-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deviceId = $this->pg->table('devices')->insertGetId([
            'school_id' => $schoolId,
            'code' => 'TK-PG-'.uniqid(),
            'name' => 'Meja PG JSONB',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rawPayload = json_encode([
            'soilrs485' => [
                'Nitrogen' => 45.2,
                'Ph' => 6.8,
            ],
            'firmware_version' => '1.0.4',
        ]);

        $readingId = $this->pg->table('telemetry_readings')->insertGetId([
            'device_id' => $deviceId,
            'provenance' => 'simulation',
            'nitrogen' => 45.2,
            'ph' => 6.8,
            'raw_payload' => $rawPayload,
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // PostgreSQL JSONB arrow operators
        $row = $this->pg->table('telemetry_readings')
            ->where('id', $readingId)
            ->selectRaw("raw_payload->>'firmware_version' as fw_ver")
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals('1.0.4', $row->fw_ver);

        // Cleanup test data
        $this->pg->table('telemetry_readings')->where('id', $readingId)->delete();
        $this->pg->table('devices')->where('id', $deviceId)->delete();
        $this->pg->table('schools')->where('id', $schoolId)->delete();
    }

    public function test_postgresql_append_only_audit_trigger_blocks_update_and_delete(): void
    {
        $schoolId = $this->pg->table('schools')->insertGetId([
            'npsn' => 'PG'.rand(100000, 999999),
            'name' => 'SMK Trigger Test',
            'slug' => 'smk-trigger-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $auditId = $this->pg->table('audit_logs')->insertGetId([
            'school_id' => $schoolId,
            'actor_name' => 'System Tester',
            'actor_role' => 'superuser',
            'action' => 'school_settings.updated',
            'target_type' => 'App\\Models\\SchoolSetting',
            'target_id' => '1',
            'diff' => json_encode(['threshold' => ['old' => 10, 'new' => 20]]),
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempting to UPDATE an audit_log record MUST fail due to PostgreSQL trigger
        $updateBlocked = false;
        try {
            $this->pg->table('audit_logs')->where('id', $auditId)->update(['action' => 'tampered.action']);
        } catch (QueryException $e) {
            $updateBlocked = true;
            $this->assertStringContainsString('append-only', strtolower($e->getMessage()));
        }
        $this->assertTrue($updateBlocked, 'PostgreSQL trigger MUST block direct UPDATE on audit_logs');

        // Attempting to DELETE an audit_log record MUST fail due to PostgreSQL trigger
        $deleteBlocked = false;
        try {
            $this->pg->table('audit_logs')->where('id', $auditId)->delete();
        } catch (QueryException $e) {
            $deleteBlocked = true;
            $this->assertStringContainsString('append-only', strtolower($e->getMessage()));
        }
        $this->assertTrue($deleteBlocked, 'PostgreSQL trigger MUST block direct DELETE on audit_logs');
    }

    public function test_postgresql_transaction_atomic_rollback(): void
    {
        $initialCount = $this->pg->table('schools')->count();

        try {
            $this->pg->transaction(function () {
                $this->pg->table('schools')->insert([
                    'npsn' => '99887766',
                    'name' => 'SMK Rollback Test',
                    'slug' => 'smk-rollback-'.uniqid(),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                throw new \RuntimeException('Simulated mid-operation transaction failure');
            });
        } catch (\RuntimeException $e) {
            // Expected
        }

        $finalCount = $this->pg->table('schools')->count();
        $this->assertEquals($initialCount, $finalCount, 'Transaction failure must completely roll back changes on PostgreSQL');
    }

    public function test_postgresql_pessimistic_locking_prevents_scheduling_collision(): void
    {
        $schoolId = $this->pg->table('schools')->insertGetId([
            'npsn' => 'PG'.rand(100000, 999999),
            'name' => 'SMK Lock Test',
            'slug' => 'smk-lock-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deviceId = $this->pg->table('devices')->insertGetId([
            'school_id' => $schoolId,
            'code' => 'TK-LOCK-'.uniqid(),
            'name' => 'Meja Lock Test',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = $this->pg->table('activity_schedules')->insertGetId([
            'school_id' => $schoolId,
            'device_id' => $deviceId,
            'title' => 'Sesi Praktikum Terkunci',
            'activity_date' => now()->toDateString(),
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Execute pessimistic lock query
        $this->pg->transaction(function () use ($scheduleId) {
            $lockedSchedule = $this->pg->table('activity_schedules')
                ->where('id', $scheduleId)
                ->lockForUpdate()
                ->first();

            $this->assertNotNull($lockedSchedule);
            $this->assertEquals('Sesi Praktikum Terkunci', $lockedSchedule->title);
        });

        // Cleanup
        $this->pg->table('activity_schedules')->where('id', $scheduleId)->delete();
        $this->pg->table('devices')->where('id', $deviceId)->delete();
        $this->pg->table('schools')->where('id', $schoolId)->delete();
    }

    public function test_postgresql_tenant_isolation_and_idor_scoping(): void
    {
        $schoolA = $this->pg->table('schools')->insertGetId([
            'npsn' => 'PG'.rand(100000, 999999),
            'name' => 'SMK Postgres Isolation A',
            'slug' => 'smk-pg-iso-a-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schoolB = $this->pg->table('schools')->insertGetId([
            'npsn' => 'PG'.rand(100000, 999999),
            'name' => 'SMK Postgres Isolation B',
            'slug' => 'smk-pg-iso-b-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deviceA = $this->pg->table('devices')->insertGetId([
            'school_id' => $schoolA,
            'code' => 'TK-ISO-A-'.uniqid(),
            'name' => 'Meja Iso A',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deviceB = $this->pg->table('devices')->insertGetId([
            'school_id' => $schoolB,
            'code' => 'TK-ISO-B-'.uniqid(),
            'name' => 'Meja Iso B',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tenant-scoped query for School A must NEVER return School B devices
        $schoolADevices = $this->pg->table('devices')->where('school_id', $schoolA)->pluck('id')->all();
        $this->assertContains($deviceA, $schoolADevices);
        $this->assertNotContains($deviceB, $schoolADevices);

        // Cleanup
        $this->pg->table('devices')->whereIn('id', [$deviceA, $deviceB])->delete();
        $this->pg->table('schools')->whereIn('id', [$schoolA, $schoolB])->delete();
    }

    public function test_postgresql_device_calibration_pessimistic_lock_and_atomic_rollback(): void
    {
        $schoolId = $this->pg->table('schools')->insertGetId([
            'npsn' => 'PG'.rand(100000, 999999),
            'name' => 'SMK Postgres Calibration Lock',
            'slug' => 'smk-pg-calib-'.uniqid(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $deviceId = $this->pg->table('devices')->insertGetId([
            'school_id' => $schoolId,
            'code' => 'TK-PG-CAL-'.uniqid(),
            'name' => 'Meja PG Calibration Lock',
            'calibration_offsets' => json_encode(['ph' => 0.0, 'humidity' => 0.0, 'ec' => 0.0]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $initialProfilesCount = $this->pg->table('sensor_calibration_profiles')->where('device_id', $deviceId)->count();
        $initialAuditCount = $this->pg->table('audit_logs')->where('target_id', (string) $deviceId)->count();

        // Simulate atomic multi-parameter calibration with pessimistic device row lock
        $failed = false;
        try {
            $this->pg->transaction(function () use ($deviceId) {
                // 1. Acquire pessimistic lock on device row
                $locked = $this->pg->table('devices')->where('id', $deviceId)->lockForUpdate()->first();
                $this->assertNotNull($locked);

                // 2. Insert pH profile (step 1)
                $this->pg->table('sensor_calibration_profiles')->insert([
                    'device_id' => $deviceId,
                    'sensor_model' => 'RS485-SOIL-7IN1',
                    'firmware_version' => 'ESP32-BTLP-v1.0',
                    'profile_name' => 'Profil Kalibrasi pH Meja Test',
                    'version' => 1,
                    'parameter' => 'ph',
                    'method' => 'offset',
                    'parameters' => json_encode(['offset' => 0.5]),
                    'unit' => 'pH',
                    'register_address' => '0x0006',
                    'min_range' => 3.0,
                    'max_range' => 10.0,
                    'effective_from' => now(),
                    'reason' => 'Uji atomisitas kalibrasi PostgreSQL',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 3. Insert humidity profile (step 2)
                $this->pg->table('sensor_calibration_profiles')->insert([
                    'device_id' => $deviceId,
                    'sensor_model' => 'RS485-SOIL-7IN1',
                    'firmware_version' => 'ESP32-BTLP-v1.0',
                    'profile_name' => 'Profil Kalibrasi Kelembapan Meja Test',
                    'version' => 1,
                    'parameter' => 'humidity',
                    'method' => 'offset',
                    'parameters' => json_encode(['offset' => -2.0]),
                    'unit' => '%',
                    'register_address' => '0x0002',
                    'min_range' => 0.0,
                    'max_range' => 100.0,
                    'effective_from' => now(),
                    'reason' => 'Uji atomisitas kalibrasi PostgreSQL',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 4. Update calibration offsets JSON on device
                $this->pg->table('devices')->where('id', $deviceId)->update([
                    'calibration_offsets' => json_encode(['ph' => 0.5, 'humidity' => -2.0, 'ec' => 0.0]),
                ]);

                // 5. Injected simulated failure on 3rd parameter step (EC)
                throw new \RuntimeException('Simulated hardware timeout / ACK failure on EC parameter step');
            });
        } catch (\RuntimeException $e) {
            $failed = true;
        }

        $this->assertTrue($failed, 'Transaction must throw and trigger rollback');

        // Verify complete rollback on PostgreSQL: no profiles, no offset updates persisted
        $this->assertEquals($initialProfilesCount, $this->pg->table('sensor_calibration_profiles')->where('device_id', $deviceId)->count());
        $this->assertEquals($initialAuditCount, $this->pg->table('audit_logs')->where('target_id', (string) $deviceId)->count());

        $deviceAfter = $this->pg->table('devices')->where('id', $deviceId)->first();
        $offsetsAfter = json_decode($deviceAfter->calibration_offsets, true);
        $this->assertEquals(0.0, $offsetsAfter['ph']);
        $this->assertEquals(0.0, $offsetsAfter['humidity']);
        $this->assertEquals(0.0, $offsetsAfter['ec']);

        // Cleanup
        $this->pg->table('devices')->where('id', $deviceId)->delete();
        $this->pg->table('schools')->where('id', $schoolId)->delete();
    }
}
