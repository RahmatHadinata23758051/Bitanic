<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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
                            {--force : Execute without confirmation in production}';

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

        if ($isRollback) {
            return $this->handleRollback($isDryRun);
        }

        return $this->handleMigration($isDryRun);
    }

    /**
     * Handle forward migration.
     */
    protected function handleMigration(bool $isDryRun): int
    {
        $adminUsers = User::where('role', 'admin')->get();
        $superuserWithSchool = User::where('role', 'superuser')->whereNotNull('school_id')->get();

        $this->info('--- BITANIC RBAC MIGRATION: admin -> admin_sekolah & superuser dissociation ---');
        $this->line("Found {$adminUsers->count()} legacy 'admin' user(s).");
        $this->line("Found {$superuserWithSchool->count()} 'superuser' user(s) with school association.");

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

        $snapshotDir = storage_path('app/rbac_migration_snapshots');
        if (! File::exists($snapshotDir)) {
            File::makeDirectory($snapshotDir, 0755, true);
        }

        $timestamp = now()->format('Ymd_His');
        $snapshotPath = "{$snapshotDir}/rbac_migration_snapshot_{$timestamp}.json";

        $snapshotData = [
            'created_at' => now()->toIso8601String(),
            'total_users' => $allAffected->count(),
            'users' => $allAffected->map(function (User $u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role,
                    'school_id' => $u->school_id,
                ];
            })->values()->toArray(),
        ];

        File::put($snapshotPath, json_encode($snapshotData, JSON_PRETTY_PRINT));
        $this->info("Captured pre-migration snapshot at: {$snapshotPath}");

        DB::transaction(function () {
            // Update admin to admin_sekolah
            User::where('role', 'admin')->update(['role' => 'admin_sekolah']);

            // Dissociate superusers from specific school
            User::where('role', 'superuser')->whereNotNull('school_id')->update(['school_id' => null]);
        });

        $this->info('Migration successfully executed.');
        $this->line("Legacy 'admin' accounts converted to 'admin_sekolah'.");
        $this->line('Superuser accounts dissociated from school tenancy (school_id = NULL).');

        return Command::SUCCESS;
    }

    /**
     * Handle rollback from snapshot.
     */
    protected function handleRollback(bool $isDryRun): int
    {
        $snapshotPath = $this->option('snapshot');

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

            rsort($files);
            $snapshotPath = $files[0];
        }

        if (! File::exists($snapshotPath)) {
            $this->error("Snapshot file not found: {$snapshotPath}");

            return Command::FAILURE;
        }

        $this->info("Using snapshot file: {$snapshotPath}");
        $content = File::get($snapshotPath);
        $data = json_decode($content, true);

        if (! isset($data['users']) || ! is_array($data['users'])) {
            $this->error('Invalid snapshot file format.');

            return Command::FAILURE;
        }

        $users = $data['users'];
        $this->line('Found '.count($users).' user account(s) in snapshot to restore.');

        $tableData = array_map(function ($u) {
            return [
                'ID' => $u['id'],
                'Name' => $u['name'],
                'Email' => $u['email'],
                'Restore Role' => $u['role'],
                'Restore School ID' => $u['school_id'] ?? 'NULL',
            ];
        }, $users);

        $this->table(['ID', 'Name', 'Email', 'Restore Role', 'Restore School ID'], $tableData);

        if ($isDryRun) {
            $this->warn('DRY RUN: No rollback changes were applied.');

            return Command::SUCCESS;
        }

        DB::transaction(function () use ($users) {
            foreach ($users as $record) {
                User::where('id', $record['id'])->update([
                    'role' => $record['role'],
                    'school_id' => $record['school_id'],
                ]);
            }
        });

        $this->info('Rollback successfully completed. Restored accounts to their exact snapshot state.');

        return Command::SUCCESS;
    }
}
