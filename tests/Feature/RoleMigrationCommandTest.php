<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleMigrationCommandTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::where('slug', 'smk-ppn-1-sukaraja')->first() ?? School::create([
            'name' => 'SMK PPN 1 Sukaraja',
            'slug' => 'smk-ppn-1-sukaraja',
            'status' => 'active',
        ]);
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
}
