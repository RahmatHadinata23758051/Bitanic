<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleMigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        File::ensureDirectoryExists(storage_path('app/rbac_migration_snapshots'));
        File::cleanDirectory(storage_path('app/rbac_migration_snapshots'));

        $this->school = School::where('slug', 'smk-ppn-1-sukaraja')->first() ?? School::create([
            'name' => 'SMK PPN 1 Sukaraja',
            'slug' => 'smk-ppn-1-sukaraja',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        if (File::exists(storage_path('app/rbac_migration_snapshots'))) {
            File::cleanDirectory(storage_path('app/rbac_migration_snapshots'));
        }

        parent::tearDown();
    }

    public function test_dry_run_leaves_database_unmodified(): void
    {
        $admin = User::create([
            'name' => 'Legacy Admin',
            'email' => 'legacy.admin@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin',
            'school_id' => $this->school->id,
        ]);

        $superuser = User::create([
            'name' => 'Platform Superuser',
            'email' => 'superuser@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'superuser',
            'school_id' => $this->school->id,
        ]);

        $this->artisan('bitanic:migrate-school-admin-roles --dry-run')
            ->expectsOutputToContain('DRY RUN: No database changes were applied.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
            'school_id' => $this->school->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $superuser->id,
            'role' => 'superuser',
            'school_id' => $this->school->id,
        ]);
    }

    public function test_execution_migrates_roles_and_creates_snapshot(): void
    {
        $admin = User::create([
            'name' => 'Legacy Admin',
            'email' => 'legacy.admin@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin',
            'school_id' => $this->school->id,
        ]);

        $superuser = User::create([
            'name' => 'Platform Superuser',
            'email' => 'superuser@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'superuser',
            'school_id' => $this->school->id,
        ]);

        $this->artisan('bitanic:migrate-school-admin-roles')
            ->expectsOutputToContain('Captured pre-migration snapshot at:')
            ->expectsOutputToContain("Legacy 'admin' accounts converted to 'admin_sekolah'.")
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin_sekolah',
            'school_id' => $this->school->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $superuser->id,
            'role' => 'superuser',
            'school_id' => null,
        ]);
    }

    public function test_execution_is_idempotent(): void
    {
        // First migrate
        $admin = User::create([
            'name' => 'Legacy Admin',
            'email' => 'legacy.admin@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin',
            'school_id' => $this->school->id,
        ]);

        $this->artisan('bitanic:migrate-school-admin-roles')->assertSuccessful();

        // Second run
        $this->artisan('bitanic:migrate-school-admin-roles')
            ->expectsOutputToContain('No accounts require migration. Database is already in target state.')
            ->assertSuccessful();
    }

    public function test_rollback_restores_exact_snapshot_state(): void
    {
        $admin = User::create([
            'name' => 'Legacy Admin',
            'email' => 'legacy.admin@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin',
            'school_id' => $this->school->id,
        ]);

        $superuser = User::create([
            'name' => 'Platform Superuser',
            'email' => 'superuser@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'superuser',
            'school_id' => $this->school->id,
        ]);

        // Migrate
        $this->artisan('bitanic:migrate-school-admin-roles')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin_sekolah',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $superuser->id,
            'school_id' => null,
        ]);

        // Rollback
        $this->artisan('bitanic:migrate-school-admin-roles --rollback')
            ->expectsOutputToContain('Rollback successfully completed. Restored accounts to their exact snapshot state.')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
            'school_id' => $this->school->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $superuser->id,
            'role' => 'superuser',
            'school_id' => $this->school->id,
        ]);
    }

    public function test_rollback_detects_subsequent_modifications_and_aborts_without_force(): void
    {
        $admin = User::create([
            'name' => 'Legacy Admin To Modify',
            'email' => 'mod.admin@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin',
            'school_id' => $this->school->id,
        ]);

        // 1. Forward migration
        $this->artisan('bitanic:migrate-school-admin-roles')->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin_sekolah',
        ]);

        // 2. Simulate subsequent legitimate change: User role is changed to 'guru'
        $admin->update(['role' => 'guru']);

        // 3. Rollback without --force: MUST detect conflict and abort
        $this->artisan('bitanic:migrate-school-admin-roles --rollback')
            ->expectsOutputToContain('ROLLBACK CONFLICT DETECTED')
            ->expectsOutputToContain('Rollback aborted to prevent overwriting subsequent legitimate user changes')
            ->assertFailed();

        // 4. Verify DB was NOT corrupted: role remains 'guru'
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'guru',
        ]);
    }

    public function test_rollback_with_force_overrides_conflict_detection(): void
    {
        $admin = User::create([
            'name' => 'Legacy Admin To Force',
            'email' => 'force.admin@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin',
            'school_id' => $this->school->id,
        ]);

        // 1. Forward migration
        $this->artisan('bitanic:migrate-school-admin-roles')->assertSuccessful();

        // 2. Modify user
        $admin->update(['role' => 'guru']);

        // 3. Rollback WITH --force
        $this->artisan('bitanic:migrate-school-admin-roles --rollback --force')
            ->expectsOutputToContain('ROLLBACK CONFLICT DETECTED')
            ->expectsOutputToContain('--force passed: Overriding conflict detection')
            ->expectsOutputToContain('Rollback successfully completed')
            ->assertSuccessful();

        // 4. Verify restored to exact prior state: role is 'admin'
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
            'school_id' => $this->school->id,
        ]);
    }

    public function test_snapshot_identity_is_unique_and_contains_checksum(): void
    {
        User::create([
            'name' => 'Snapshot Admin',
            'email' => 'snapshot.admin@bitanic.test',
            'password' => Hash::make('P@ssword123!'),
            'role' => 'admin',
            'school_id' => $this->school->id,
        ]);

        $this->artisan('bitanic:migrate-school-admin-roles')->assertSuccessful();

        $snapshotDir = storage_path('app/rbac_migration_snapshots');
        $files = File::glob("{$snapshotDir}/rbac_migration_snapshot_*.json");
        $this->assertNotEmpty($files);

        rsort($files);
        $latest = $files[0];
        $content = json_decode((string) File::get($latest), true);

        $this->assertArrayHasKey('snapshot_id', $content);
        $this->assertArrayHasKey('checksum', $content);
        $this->assertArrayHasKey('total_users', $content);
        $this->assertArrayHasKey('users', $content);
        $this->assertGreaterThan(0, $content['total_users']);
    }
}
