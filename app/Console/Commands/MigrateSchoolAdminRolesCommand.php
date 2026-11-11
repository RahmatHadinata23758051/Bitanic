<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MigrateSchoolAdminRolesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bitanic:migrate-school-admin-roles
                            {--dry-run : Simulate the migration without writing to the database}
                            {--rollback : Roll back changes using a saved snapshot}
                            {--snapshot= : Specific snapshot filepath to rollback from}
                            {--force : Force execution or override conflict detection}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy admin roles to admin_sekolah and clear superuser school association';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isRollback = (bool) $this->option('rollback');

        // Preflight 1: Validate database connection
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('Preflight check failed: Unable to connect to the database. Error: '.$e->getMessage());

            return Command::FAILURE;
        }

        if ($isRollback) {
            return $this->handleRollback($isDryRun);
        }

        return $this->handleMigration($isDryRun);
    }

    /**
     * Handle forward migration with durable, verified snapshot and transaction isolation.
     */
    protected function handleMigration(bool $isDryRun): int
    {
        $adminUsers = User::where('role', 'admin')->get();
        $superuserWithSchool = User::where('role', 'superuser')->whereNotNull('school_id')->get();

        $this->info('--- BITANIC RBAC MIGRATION: admin -> admin_sekolah & superuser dissociation ---');
        $this->line("Found {$adminUsers->count()} legacy 'admin' user(s).");
        $this->line("Found {$superuserWithSchool->count()} 'superuser' user(s) with school association.");

        // Idempotent check
        if ($adminUsers->isEmpty() && $superuserWithSchool->isEmpty()) {
            $this->info('No accounts require migration. Database is already in target state.');

            return Command::SUCCESS;
        }

        $allAffected = $adminUsers->concat($superuserWithSchool);

        $tableData = $allAffected->map(function (User $u) {
            return [
                'ID' => $u->id,
                'Name' => $u->name,
                'Email' => $u->email,
                'Current Role' => $u->role,
                'Target Role' => $u->role === 'admin' ? 'admin_sekolah' : 'superuser',
                'Current School ID' => $u->school_id ?? 'NULL',
                'Target School ID' => $u->role === 'superuser' ? 'NULL' : ($u->school_id ?? 'NULL'),
            ];
        });

        $this->table(['ID', 'Name', 'Email', 'Current Role', 'Target Role', 'Current School ID', 'Target School ID'], $tableData);

        if ($isDryRun) {
            $this->warn('DRY RUN: No database changes were applied.');

            return Command::SUCCESS;
        }

        // Preflight 2: Verify snapshot directory write permissions
        $snapshotDir = storage_path('app/rbac_migration_snapshots');
        if (! File::exists($snapshotDir)) {
            File::makeDirectory($snapshotDir, 0755, true);
        }

        if (! is_writable($snapshotDir)) {
            $this->error("Preflight check failed: Snapshot directory is not writable ({$snapshotDir}).");

            return Command::FAILURE;
        }

        // Unique Snapshot Identity (Microsecond Timestamp + UUID to prevent collision and guarantee chronological sorting)
        $snapshotId = (string) Str::uuid();
        $timestamp = now()->format('Ymd_His_u');
        $snapshotPath = "{$snapshotDir}/rbac_migration_snapshot_{$timestamp}_{$snapshotId}.json";

        $usersPayload = $allAffected->map(function (User $u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'original_role' => $u->role,
                'original_school_id' => $u->school_id,
                'expected_target_role' => $u->role === 'admin' ? 'admin_sekolah' : 'superuser',
                'expected_target_school_id' => $u->role === 'superuser' ? null : $u->school_id,
            ];
        })->values()->toArray();

        $serializedUsers = json_encode($usersPayload, JSON_UNESCAPED_SLASHES);
        $checksum = hash('sha256', (string) $serializedUsers);

        $snapshotData = [
            'snapshot_id' => $snapshotId,
            'created_at' => now()->toIso8601String(),
            'total_users' => count($usersPayload),
            'checksum' => $checksum,
            'users' => $usersPayload,
        ];

        // Write and verify snapshot durability before database mutation
        $rawJson = json_encode($snapshotData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        File::put($snapshotPath, (string) $rawJson);

        // Verification: Ensure snapshot is readable on disk and checksum matches
        $verifyContent = File::get($snapshotPath);
        $decoded = json_decode((string) $verifyContent, true);
        if (! $decoded || ! isset($decoded['checksum']) || $decoded['checksum'] !== $checksum) {
            $this->error('Preflight check failed: Snapshot write verification failed. Aborting without modifying database.');
            if (File::exists($snapshotPath)) {
                File::delete($snapshotPath);
            }

            return Command::FAILURE;
        }

        $this->info("Captured pre-migration snapshot at: {$snapshotPath}");

        // Atomic DB transaction
        try {
            DB::transaction(function () {
                // Update admin to admin_sekolah
                User::where('role', 'admin')->update(['role' => 'admin_sekolah']);

                // Dissociate superusers from specific school
                User::where('role', 'superuser')->whereNotNull('school_id')->update(['school_id' => null]);
            });
        } catch (\Throwable $e) {
            $this->error('Database transaction failed: '.$e->getMessage().'. Removing unapplied snapshot.');
            if (File::exists($snapshotPath)) {
                File::delete($snapshotPath);
            }

            return Command::FAILURE;
        }

        $this->info('Migration successfully executed.');
        $this->line("Legacy 'admin' accounts converted to 'admin_sekolah'.");
        $this->line('Superuser accounts dissociated from school tenancy (school_id = NULL).');

        return Command::SUCCESS;
    }

    /**
     * Handle rollback from snapshot with conflict detection and exact prior-state restoration.
     */
    protected function handleRollback(bool $isDryRun): int
    {
        $snapshotPath = $this->option('snapshot');
        $force = (bool) $this->option('force');

        if (! $snapshotPath) {
            $snapshotDir = storage_path('app/rbac_migration_snapshots');
            if (! File::exists($snapshotDir)) {
                $this->error("Snapshot directory not found at: {$snapshotDir}");

                return Command::FAILURE;
            }

            $files = File::glob("{$snapshotDir}/rbac_migration_snapshot_*.json");
            if (empty($files)) {
                $this->error('No snapshot files found for rollback.');

                return Command::FAILURE;
            }

            // Sort descending by file modification time, falling back to reverse filename
            usort($files, function ($a, $b) {
                $timeDiff = filemtime($b) <=> filemtime($a);
                if ($timeDiff !== 0) {
                    return $timeDiff;
                }

                return strcmp($b, $a);
            });
            $snapshotPath = $files[0];
        }

        if (! File::exists($snapshotPath)) {
            $this->error("Snapshot file not found: {$snapshotPath}");

            return Command::FAILURE;
        }

        $this->info("Using snapshot file: {$snapshotPath}");
        $content = File::get($snapshotPath);
        $data = json_decode((string) $content, true);

        if (! isset($data['users']) || ! is_array($data['users'])) {
            $this->error('Invalid snapshot file format: Missing users array.');

            return Command::FAILURE;
        }

        // Verify checksum integrity
        $users = $data['users'];
        if (isset($data['checksum'])) {
            $serializedUsers = json_encode($users, JSON_UNESCAPED_SLASHES);
            $calculatedChecksum = hash('sha256', (string) $serializedUsers);
            if (! hash_equals($data['checksum'], $calculatedChecksum)) {
                $this->error('Snapshot corruption detected: Checksum does not match snapshot contents.');

                return Command::FAILURE;
            }
        }

        $this->line('Found '.count($users).' user account(s) in snapshot to restore.');

        // Conflict Detection: Inspect current user state against expected target state
        $conflicts = [];
        $restorePlan = [];

        foreach ($users as $record) {
            $userId = $record['id'];
            $email = $record['email'] ?? "ID {$userId}";
            $currentUser = User::find($userId);

            $originalRole = $record['original_role'] ?? ($record['role'] ?? null);
            $originalSchoolId = array_key_exists('original_school_id', $record)
                ? $record['original_school_id']
                : ($record['school_id'] ?? null);

            $expectedTargetRole = $record['expected_target_role'] ?? ($originalRole === 'admin' ? 'admin_sekolah' : 'superuser');
            $expectedTargetSchoolId = array_key_exists('expected_target_school_id', $record)
                ? $record['expected_target_school_id']
                : ($originalRole === 'superuser' ? null : $originalSchoolId);

            if (! $currentUser) {
                $conflicts[] = [
                    'ID' => $userId,
                    'Email' => $email,
                    'Issue' => 'User deleted from database',
                    'Current' => 'Record not found',
                    'Expected' => "role={$expectedTargetRole}, school_id=".($expectedTargetSchoolId ?? 'NULL'),
                ];

                continue;
            }

            $currentSchoolIdStr = $currentUser->school_id === null ? 'NULL' : (string) $currentUser->school_id;
            $expectedSchoolIdStr = $expectedTargetSchoolId === null ? 'NULL' : (string) $expectedTargetSchoolId;

            $roleMismatch = $currentUser->role !== $expectedTargetRole;
            $schoolMismatch = $currentSchoolIdStr !== $expectedSchoolIdStr;

            if ($roleMismatch || $schoolMismatch) {
                $conflicts[] = [
                    'ID' => $userId,
                    'Email' => $currentUser->email,
                    'Issue' => 'Subsequent modification detected',
                    'Current' => "role={$currentUser->role}, school_id={$currentSchoolIdStr}",
                    'Expected' => "role={$expectedTargetRole}, school_id={$expectedSchoolIdStr}",
                ];
            }

            $restorePlan[] = [
                'user' => $currentUser,
                'id' => $userId,
                'name' => $currentUser->name,
                'email' => $currentUser->email,
                'restore_role' => $originalRole,
                'restore_school_id' => $originalSchoolId,
            ];
        }

        // If conflicts detected and --force is not active: abort safely
        if (! empty($conflicts)) {
            $this->warn('--- ROLLBACK CONFLICT DETECTED ---');
            $this->line('The following user account(s) have been modified or removed since the migration was executed:');
            $this->table(['ID', 'Email', 'Issue', 'Current State', 'Expected Post-Migration'], $conflicts);

            if (! $force) {
                $this->error('Rollback aborted to prevent overwriting subsequent legitimate user changes. Pass --force to override conflict detection.');

                return Command::FAILURE;
            }

            $this->warn('--force passed: Overriding conflict detection and proceeding with restoration.');
        }

        $tableData = array_map(function ($p) {
            return [
                'ID' => $p['id'],
                'Name' => $p['name'],
                'Email' => $p['email'],
                'Restore Role' => $p['restore_role'],
                'Restore School ID' => $p['restore_school_id'] ?? 'NULL',
            ];
        }, $restorePlan);

        $this->table(['ID', 'Name', 'Email', 'Restore Role', 'Restore School ID'], $tableData);

        if ($isDryRun) {
            $this->warn('DRY RUN: No rollback changes were applied.');

            return Command::SUCCESS;
        }

        // Atomic Rollback Execution
        DB::transaction(function () use ($restorePlan) {
            foreach ($restorePlan as $record) {
                User::where('id', $record['id'])->update([
                    'role' => $record['restore_role'],
                    'school_id' => $record['restore_school_id'],
                ]);
            }
        });

        $this->info('Rollback successfully completed. Restored accounts to their exact snapshot state.');

        return Command::SUCCESS;
    }
}
