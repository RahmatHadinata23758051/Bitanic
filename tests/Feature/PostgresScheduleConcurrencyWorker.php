<?php

declare(strict_types=1);

use App\Actions\Schedule\CreateScheduleAction;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

if (($argv[1] ?? null) !== '--concurrency-worker') {
    exit(0);
}

$state = json_decode((string) file_get_contents($argv[2] ?? ''), true, flags: JSON_THROW_ON_ERROR);
$projectPath = $state['project_path'];
require $projectPath.'/vendor/autoload.php';
$app = require $projectPath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'pgsql_testing']);
DB::purge('pgsql_testing');
DB::setDefaultConnection('pgsql_testing');

$connection = DB::connection('pgsql_testing');
if ($connection->getDatabaseName() !== 'bitanic_test') {
    fwrite(STDERR, 'Refusing worker outside isolated bitanic_test.');
    exit(20);
}

$pid = (int) $connection->getPdo()->query('SELECT pg_backend_pid()')->fetchColumn();
file_put_contents($state['pid_file'], (string) $pid);
$connection->statement("SET lock_timeout TO '6000ms'");
$connection->statement("SET statement_timeout TO '10000ms'");

try {
    $schedule = app(CreateScheduleAction::class)->execute([
        'school_id' => $state['school_id'],
        'classroom_id' => $state['classroom_id'],
        'device_id' => $state['device_id'],
        'title' => $state['worker_title'],
        'activity_date' => $state['date'],
        'start_time' => '09:00',
        'end_time' => '11:00',
    ], User::findOrFail($state['teacher_id']));
    $result = ['status' => 'accepted', 'schedule_id' => $schedule->id, 'pid' => $pid];
    $exitCode = 10;
} catch (ValidationException $exception) {
    $result = ['status' => 'rejected', 'errors' => $exception->errors(), 'pid' => $pid];
    $exitCode = 0;
} catch (Throwable $exception) {
    $result = ['status' => 'error', 'error' => $exception->getMessage(), 'class' => $exception::class, 'pid' => $pid];
    $exitCode = 11;
}

file_put_contents($state['result_file'], json_encode($result, JSON_THROW_ON_ERROR));
exit($exitCode);
