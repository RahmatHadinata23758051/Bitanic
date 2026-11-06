<?php

namespace Tests\Feature;

use App\Models\ActivitySchedule;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostgresScheduleConcurrencyRaceTest extends TestCase
{
    public function test_independent_postgres_sessions_serialize_overlapping_device_bookings(): void
    {
        if (config('database.default') !== 'pgsql_testing') {
            $this->markTestSkipped('Run this integration test with DB_CONNECTION=pgsql_testing against isolated bitanic_test.');
        }

        $connection = DB::connection('pgsql_testing');
        if ($connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== 'bitanic_test') {
            $this->markTestSkipped('This integration test runs only against isolated bitanic_test.');
        }
        $version = $connection->selectOne("SELECT current_setting('server_version_num') AS version");
        $this->assertGreaterThanOrEqual(170000, (int) $version->version);
        $this->assertLessThan(180000, (int) $version->version);
        DB::setDefaultConnection('pgsql_testing');
        config(['database.default' => 'pgsql_testing']);

        $db = DB::connection('pgsql_testing');
        $token = strtolower((string) Str::uuid());
        $workerTitle = 'Automated concurrency worker '.$token;
        $firstTitle = 'Automated concurrency committed '.$token;
        $paths = [];
        $school = $device = $teacher = $classroom = $first = null;
        $process = null;
        $pipes = [];
        $workerOutput = '';

        try {
            foreach (['state', 'pid', 'result', 'stdout', 'stderr'] as $name) {
                $path = tempnam(sys_get_temp_dir(), 'bitanic-race-'.$name.'-');
                if ($path === false) {
                    throw new \RuntimeException('Could not allocate a temporary race fixture path: '.$name);
                }
                $paths[$name] = $path;
                if (! unlink($path)) {
                    throw new \RuntimeException('Could not prepare a fresh temporary race fixture path: '.$name);
                }
            }

            $school = School::create([
                'name' => 'Automated concurrency '.$token,
                'slug' => 'automated-concurrency-'.$token,
                'status' => 'active',
            ]);
            $device = Device::create([
                'school_id' => $school->id,
                'code' => 'RACE-'.substr($token, 0, 16),
                'name' => 'Automated concurrency fixture',
                'is_active' => true,
            ]);
            $teacher = User::create([
                'school_id' => $school->id,
                'name' => 'Automated concurrency teacher',
                'email' => 'automated-race-'.$token.'@example.invalid',
                'password' => Hash::make(Str::random(40)),
                'role' => 'guru',
            ]);
            $classroom = Classroom::create([
                'school_id' => $school->id,
                'name' => 'Automated concurrency '.$token,
            ]);

            $statePath = $paths['state'];
            $date = now()->addDays(32)->toDateString();
            file_put_contents($statePath, json_encode([
                'project_path' => base_path(),
                'school_id' => $school->id,
                'device_id' => $device->id,
                'teacher_id' => $teacher->id,
                'classroom_id' => $classroom->id,
                'date' => $date,
                'worker_title' => $workerTitle,
                'pid_file' => $paths['pid'],
                'result_file' => $paths['result'],
            ], JSON_THROW_ON_ERROR));

            $db->beginTransaction();
            $db->selectOne('SELECT id FROM devices WHERE id = ? FOR UPDATE', [$device->id]);
            $mainBackendPid = (int) $db->getPdo()->query('SELECT pg_backend_pid()')->fetchColumn();
            $descriptors = [
                0 => ['file', 'NUL', 'r'],
                1 => ['file', $paths['stdout'], 'a'],
                2 => ['file', $paths['stderr'], 'a'],
            ];
            $workerScript = base_path('tests/Feature/PostgresScheduleConcurrencyWorker.php');
            $process = proc_open([PHP_BINARY, $workerScript, '--concurrency-worker', $statePath], $descriptors, $pipes, base_path());
            $this->assertIsResource($process, 'Could not start independent PostgreSQL worker process.');

            $workerBackendPid = null;
            $workerActivity = null;
            $deadline = microtime(true) + 15;
            while (microtime(true) < $deadline) {
                clearstatcache(true, $paths['pid']);
                if (is_file($paths['pid'])) {
                    $workerBackendPid = (int) file_get_contents($paths['pid']);
                    $workerActivity = $db->selectOne(
                        'SELECT pid, wait_event_type, wait_event FROM pg_stat_activity WHERE pid = ? AND datname = current_database()',
                        [$workerBackendPid]
                    );
                    if ($workerActivity?->wait_event_type === 'Lock') {
                        break;
                    }
                }

                $status = proc_get_status($process);
                if (! $status['running'] || is_file($paths['result'])) {
                    break;
                }
                usleep(100_000);
            }

            $workerOutput .= file_get_contents($paths['stdout']).file_get_contents($paths['stderr']);
            $this->assertNotNull($workerBackendPid, 'Worker did not publish a PostgreSQL backend PID. '.$workerOutput);
            $this->assertNotSame($mainBackendPid, $workerBackendPid, 'Worker must use an independent PostgreSQL session.');
            $this->assertSame('Lock', $workerActivity?->wait_event_type, 'Worker never entered a PostgreSQL lock wait. '.$workerOutput);

            $first = ActivitySchedule::create([
                'school_id' => $school->id,
                'classroom_id' => $classroom->id,
                'device_id' => $device->id,
                'title' => $firstTitle,
                'activity_date' => $date,
                'start_time' => '08:00',
                'end_time' => '10:00',
            ]);
            $db->commit();

            $deadline = microtime(true) + 15;
            while (microtime(true) < $deadline && ! is_file($paths['result'])) {
                $workerOutput .= file_get_contents($paths['stdout']).file_get_contents($paths['stderr']);
                usleep(100_000);
            }
            $this->assertFileExists($paths['result'], 'Worker did not finish after the first schedule committed. '.$workerOutput);
            $workerResult = json_decode((string) file_get_contents($paths['result']), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame('rejected', $workerResult['status'] ?? null, json_encode($workerResult, JSON_THROW_ON_ERROR));
            $this->assertArrayHasKey('device_id', $workerResult['errors'] ?? []);
            $this->assertSame(0, ActivitySchedule::query()->where('school_id', $school->id)->where('title', $workerTitle)->count());

            $workerStatus = proc_get_status($process);
            $this->assertFalse($workerStatus['running'], 'Worker must exit after writing its result.');
            proc_close($process);
            $process = null;
        } finally {
            if ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
            if (is_resource($process)) {
                proc_terminate($process);
                $deadline = microtime(true) + 3;
                do {
                    if (! proc_get_status($process)['running']) {
                        break;
                    }
                    usleep(50_000);
                } while (microtime(true) < $deadline);
                proc_close($process);
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if ($school?->exists) {
                ActivitySchedule::query()
                    ->where('school_id', $school->id)
                    ->whereIn('title', [$firstTitle, $workerTitle])
                    ->delete();
                $teacher?->delete();
                $classroom?->delete();
                $device?->delete();
                $school->delete();
            }
            foreach ($paths as $path) {
                @unlink($path);
            }
        }
    }
}
