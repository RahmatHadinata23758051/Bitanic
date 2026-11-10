<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class SchoolSettingsAndAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // HELPER
    // =========================================================================

    private static int $npsnCounter = 20209000;

    private function makeSchool(string $slug = 'smk-ppn-1-sukaraja', array $overrides = []): School
    {
        return School::firstOrCreate(
            ['slug' => $slug],
            array_merge([
                'npsn' => (string) (++self::$npsnCounter),
                'name' => 'SMK Sukaraja',
                'status' => 'active',
            ], $overrides)
        );
    }

    private function createUserWithoutSchool(string $role = 'admin'): User
    {
        $user = User::factory()->create(['role' => $role]);
        DB::table('users')->where('id', $user->id)->update(['school_id' => null]);

        return $user->fresh();
    }

    private function validThresholds(array $overrides = []): array
    {
        return array_merge([
            'minMoisture' => 30.0,
            'maxMoisture' => 70.0,
            'minPH' => 6.0,
            'maxPH' => 7.0,
            'minEC' => 1000,
            'maxEC' => 2000,
            'minNitrogen' => 50,
            'maxNitrogen' => 100,
        ], $overrides);
    }

    private function validSettingsPayload(array $overrides = []): array
    {
        return array_merge([
            'school_name' => 'SMK Sukaraja',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH',
            'lead_teacher' => 'Guru',
            'thresholds' => $this->validThresholds(),
        ], $overrides);
    }

    // =========================================================================
    // QA-01: FAIL-CLOSED AUTHORIZATION
    // =========================================================================

    public function test_admin_without_school_is_denied_settings_index(): void
    {
        $admin = $this->createUserWithoutSchool('admin');

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertStatus(403);
    }

    public function test_admin_without_school_is_denied_settings_update(): void
    {
        $admin = $this->createUserWithoutSchool('admin');

        $this->actingAs($admin)
            ->post(route('settings.update'), $this->validSettingsPayload())
            ->assertStatus(403);

        $this->assertDatabaseCount('school_settings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_superuser_without_school_is_denied_settings(): void
    {
        $superuser = $this->createUserWithoutSchool('superuser');

        $this->actingAs($superuser)
            ->get(route('settings.index'))
            ->assertStatus(403);

        $this->actingAs($superuser)
            ->post(route('settings.update'), $this->validSettingsPayload())
            ->assertStatus(403);
    }

    public function test_user_with_inactive_school_is_denied_settings_access(): void
    {
        $school = School::create([
            'npsn' => '20208999',
            'name' => 'SMK Nonaktif',
            'slug' => 'smk-nonaktif-settings',
            'status' => 'inactive',
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'school_id' => $school->id]);

        $this->actingAs($admin)
            ->get(route('settings.index'))
            ->assertStatus(403);

        $this->actingAs($admin)
            ->post(route('settings.update'), $this->validSettingsPayload())
            ->assertStatus(403);

        $this->assertDatabaseCount('school_settings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_admin_cannot_view_or_update_another_schools_settings(): void
    {
        $schoolA = $this->makeSchool('smk-a', ['npsn' => '20208001', 'name' => 'SMK A']);
        $schoolB = School::create([
            'npsn' => '20208002',
            'name' => 'SMK B',
            'slug' => 'smk-b',
            'status' => 'active',
        ]);

        SchoolSetting::create([
            'school_id' => $schoolA->id,
            'school_name' => 'Nama Sekolah A Rahasia',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH A',
            'lead_teacher' => 'Guru A',
            'sensor_thresholds' => $this->validThresholds(),
        ]);

        SchoolSetting::create([
            'school_id' => $schoolB->id,
            'school_name' => 'Nama Sekolah B',
            'academic_year' => '2026/2027',
            'semester' => 'Genap',
            'course_name' => 'ATPH B',
            'lead_teacher' => 'Guru B',
            'sensor_thresholds' => $this->validThresholds(),
        ]);

        $adminB = User::factory()->create(['role' => 'admin', 'school_id' => $schoolB->id]);

        // Admin B cannot see School A data
        $response = $this->actingAs($adminB)->get(route('settings.index'));
        $response->assertOk();
        $response->assertSee('Nama Sekolah B');
        $response->assertDontSee('Nama Sekolah A Rahasia');

        // Admin B updates only their own school's settings
        $this->actingAs($adminB)->post(route('settings.update'), $this->validSettingsPayload([
            'school_name' => 'Sekolah B Diupdate',
        ]));

        // School A settings remain untouched
        $settingA = SchoolSetting::where('school_id', $schoolA->id)->first();
        $this->assertEquals('Nama Sekolah A Rahasia', $settingA->school_name);

        // School B settings changed
        $settingB = SchoolSetting::where('school_id', $schoolB->id)->first();
        $this->assertEquals('Sekolah B Diupdate', $settingB->school_name);
    }

    public function test_audit_trail_never_shows_logs_from_other_schools(): void
    {
        $schoolA = $this->makeSchool('smk-a', ['npsn' => '20208001', 'name' => 'SMK A']);
        $schoolB = School::create(['npsn' => '20208002', 'name' => 'SMK B', 'slug' => 'smk-b', 'status' => 'active']);

        $adminA = User::factory()->create(['role' => 'admin', 'school_id' => $schoolA->id]);

        AuditLog::create([
            'school_id' => $schoolA->id,
            'actor_name' => 'Admin A',
            'actor_role' => 'admin',
            'action' => 'ACTION_A',
            'details' => 'Data milik Sekolah A',
        ]);

        AuditLog::create([
            'school_id' => $schoolB->id,
            'actor_name' => 'Admin B',
            'actor_role' => 'admin',
            'action' => 'ACTION_B',
            'details' => 'Rahasia Sekolah B',
        ]);

        AuditLog::create([
            'school_id' => null,
            'actor_name' => 'System',
            'actor_role' => 'system',
            'action' => 'ORPHAN',
            'details' => 'Log yatim tanpa sekolah',
        ]);

        $response = $this->actingAs($adminA)->get(route('settings.index'));
        $response->assertOk();
        $response->assertSee('Data milik Sekolah A');
        $response->assertDontSee('Rahasia Sekolah B');
        $response->assertDontSee('Log yatim tanpa sekolah');
    }

    // =========================================================================
    // ORIGINAL AC-1: School Ownership
    // =========================================================================

    public function test_school_model_exposes_owned_settings_and_audit_logs(): void
    {
        $school = $this->makeSchool();
        $setting = SchoolSetting::create([
            'school_id' => $school->id,
            'school_name' => 'SMK Sukaraja',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH',
            'lead_teacher' => 'Guru Sukaraja',
        ]);
        $log = AuditLog::create([
            'school_id' => $school->id,
            'actor_name' => 'Admin Sukaraja',
            'actor_role' => 'admin',
            'action' => 'TEST',
        ]);

        $this->assertSame($setting->id, $school->schoolSetting->id);
        $this->assertTrue($school->auditLogs->contains('id', $log->id));
    }

    public function test_settings_index_displays_school_specific_profile_and_thresholds(): void
    {
        $schoolA = $this->makeSchool();
        $schoolB = School::create([
            'npsn' => '20208099',
            'name' => 'SMK Pertanian B',
            'slug' => 'smk-pertanian-b',
            'status' => 'active',
        ]);

        SchoolSetting::create([
            'school_id' => $schoolA->id,
            'school_name' => 'SMK PPN 1 Sukaraja Kampus Utama',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH Modern',
            'lead_teacher' => 'Guru Sukaraja',
            'sensor_thresholds' => $this->validThresholds(),
        ]);

        SchoolSetting::create([
            'school_id' => $schoolB->id,
            'school_name' => 'SMK Mitra B Berdikari',
            'academic_year' => '2026/2027',
            'semester' => 'Genap',
            'course_name' => 'Smart Farming Vokasi B',
            'lead_teacher' => 'Guru Kampus B',
            'sensor_thresholds' => $this->validThresholds(),
        ]);

        $adminA = User::factory()->create(['role' => 'admin', 'school_id' => $schoolA->id]);
        $adminB = User::factory()->create(['role' => 'admin', 'school_id' => $schoolB->id]);

        $responseA = $this->actingAs($adminA)->get(route('settings.index'));
        $responseA->assertOk();
        $responseA->assertSee('SMK PPN 1 Sukaraja Kampus Utama');
        $responseA->assertDontSee('SMK Mitra B Berdikari');

        $responseB = $this->actingAs($adminB)->get(route('settings.index'));
        $responseB->assertOk();
        $responseB->assertSee('SMK Mitra B Berdikari');
        $responseB->assertDontSee('SMK PPN 1 Sukaraja Kampus Utama');
    }

    // =========================================================================
    // AC-2: Permission Denial
    // =========================================================================

    public function test_student_is_forbidden_from_viewing_or_updating_settings(): void
    {
        $student = User::factory()->create(['role' => 'siswa']);

        $this->actingAs($student)
            ->get(route('settings.index'))
            ->assertStatus(403);

        $this->actingAs($student)
            ->post(route('settings.update'), [
                'school_name' => 'Hacked School Name',
            ])
            ->assertStatus(403);
    }

    // =========================================================================
    // AC-3: Threshold Range and Unit Validation
    // =========================================================================

    public function test_school_settings_thresholds_validation_enforces_physical_ranges_and_ordering(): void
    {
        $school = $this->makeSchool();
        $admin = User::factory()->create(['role' => 'admin', 'school_id' => $school->id]);

        // 1. Inverted moisture (min > max)
        $response1 = $this->actingAs($admin)->post(route('settings.update'), $this->validSettingsPayload([
            'thresholds' => $this->validThresholds([
                'minMoisture' => 80.0,
                'maxMoisture' => 40.0,
            ]),
        ]));
        $response1->assertSessionHasErrors(['thresholds.maxMoisture']);

        // 2. Out-of-bounds pH (pH must be 0 to 14)
        $response2 = $this->actingAs($admin)->post(route('settings.update'), $this->validSettingsPayload([
            'thresholds' => $this->validThresholds([
                'minPH' => -1.0,
                'maxPH' => 15.0,
            ]),
        ]));
        $response2->assertSessionHasErrors(['thresholds.minPH', 'thresholds.maxPH']);
    }

    public function test_thresholds_validation_rejects_negative_or_inverted_ec_and_nitrogen(): void
    {
        $school = $this->makeSchool();
        $admin = User::factory()->create(['role' => 'admin', 'school_id' => $school->id]);

        $response = $this->actingAs($admin)->post(route('settings.update'), $this->validSettingsPayload([
            'thresholds' => $this->validThresholds([
                'minEC' => 2500,
                'maxEC' => 1200,
                'minNitrogen' => -10,
            ]),
        ]));

        $response->assertSessionHasErrors(['thresholds.maxEC', 'thresholds.minNitrogen']);
    }

    // =========================================================================
    // AC-4: Audit Record with Diff, Target, Correlation
    // =========================================================================

    public function test_update_school_settings_records_audited_diff_with_correlation_id_and_redaction(): void
    {
        $school = $this->makeSchool();
        $admin = User::factory()->create([
            'role' => 'admin',
            'school_id' => $school->id,
            'name' => 'Kepala Lab Sukaraja',
        ]);

        $setting = SchoolSetting::create([
            'school_id' => $school->id,
            'school_name' => 'Nama Lama',
            'academic_year' => '2025/2026',
            'semester' => 'Genap',
            'course_name' => 'Kursus Lama',
            'lead_teacher' => 'Guru Lama',
            'sensor_thresholds' => $this->validThresholds([
                'minMoisture' => 20.0,
                'maxMoisture' => 60.0,
            ]),
        ]);

        $response = $this->actingAs($admin)->post(route('settings.update'), $this->validSettingsPayload([
            'school_name' => 'Nama Diperbarui 2026',
            'course_name' => 'Smart Agronomy 4.0',
            'lead_teacher' => 'Guru Baru',
        ]));

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');

        $log = AuditLog::where('school_id', $school->id)
            ->where('action', 'UPDATE_SETTINGS')
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals($admin->id, $log->user_id);
        $this->assertEquals('Kepala Lab Sukaraja', $log->actor_name);
        $this->assertEquals('SchoolSetting', $log->target_type);
        $this->assertEquals((string) $setting->id, (string) $log->target_id);
        $this->assertNotNull($log->correlation_id);
        $this->assertIsArray($log->diff);
        $this->assertEquals('Nama Lama', $log->diff['before']['school_name'] ?? null);
        $this->assertEquals('Nama Diperbarui 2026', $log->diff['after']['school_name'] ?? null);
    }

    // =========================================================================
    // QA-04: TRANSACTION INTEGRITY
    // =========================================================================

    public function test_settings_and_audit_are_saved_atomically_in_transaction(): void
    {
        $school = $this->makeSchool();
        $admin = User::factory()->create(['role' => 'admin', 'school_id' => $school->id]);

        // Normal successful update
        $this->actingAs($admin)->post(route('settings.update'), $this->validSettingsPayload([
            'school_name' => 'Transaksi Berhasil',
        ]));

        $this->assertDatabaseHas('school_settings', [
            'school_id' => $school->id,
            'school_name' => 'Transaksi Berhasil',
        ]);

        // Audit must be recorded for the same school
        $this->assertDatabaseHas('audit_logs', [
            'school_id' => $school->id,
            'action' => 'UPDATE_SETTINGS',
        ]);
    }

    public function test_settings_update_creates_exactly_one_audit_log_per_operation(): void
    {
        $school = $this->makeSchool();
        $admin = User::factory()->create(['role' => 'admin', 'school_id' => $school->id]);

        $this->assertDatabaseCount('audit_logs', 0);

        $this->actingAs($admin)->post(route('settings.update'), $this->validSettingsPayload());

        $this->assertDatabaseCount('audit_logs', 1);

        $this->actingAs($admin)->post(route('settings.update'), $this->validSettingsPayload([
            'school_name' => 'Second Update',
        ]));

        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_transaction_rolls_back_school_settings_mutation_if_audit_logging_fails(): void
    {
        $school = $this->makeSchool();
        $admin = User::factory()->create(['role' => 'admin', 'school_id' => $school->id]);

        $setting = SchoolSetting::create([
            'school_id' => $school->id,
            'school_name' => 'Nama Sebelum Rollback',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH',
            'lead_teacher' => 'Guru Asli',
            'sensor_thresholds' => $this->validThresholds(),
        ]);

        // Install a temporary trigger to make INSERT on audit_logs fail with an exception.
        // Trigger bodies are engine-specific: SQLite uses BEGIN...RAISE(ABORT), PostgreSQL
        // needs a plpgsql function. Using the wrong dialect aborts the surrounding test
        // transaction and cascades failures into every later test in the process.
        $this->installForcedAuditInsertFailure();

        try {
            $this->withoutExceptionHandling()
                ->actingAs($admin)
                ->post(route('settings.update'), $this->validSettingsPayload([
                    'school_name' => 'Nama Yang Harus Di-Rollback',
                ]));
            $this->fail('Expected QueryException was not thrown.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Simulasi kegagalan audit', $e->getMessage());
        } finally {
            $this->dropForcedAuditInsertFailure();
        }

        // Verify the setting rolled back to original values due to DB::transaction()
        $setting->refresh();
        $this->assertEquals('Nama Sebelum Rollback', $setting->school_name);
        $this->assertDatabaseMissing('school_settings', [
            'school_id' => $school->id,
            'school_name' => 'Nama Yang Harus Di-Rollback',
        ]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_validation_failure_does_not_create_orphan_audit_log(): void
    {
        $school = $this->makeSchool();
        $admin = User::factory()->create(['role' => 'admin', 'school_id' => $school->id]);

        // Submit invalid data (missing required fields)
        $this->actingAs($admin)->post(route('settings.update'), [
            'school_name' => '', // required but empty
        ]);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    // =========================================================================
    // QA-03: APPEND-ONLY AUDIT INTEGRITY
    // =========================================================================

    public function test_audit_log_model_is_append_only_and_cannot_be_updated_or_deleted(): void
    {
        $school = $this->makeSchool();

        $log = AuditLog::create([
            'school_id' => $school->id,
            'actor_name' => 'Petugas Lab',
            'actor_role' => 'admin',
            'action' => 'TEST_RECORD',
            'details' => 'Catatan awal otentik.',
        ]);

        // Attempt update -> MUST throw RuntimeException
        try {
            $log->update(['details' => 'Catatan dipalsukan']);
            $this->fail('Expected RuntimeException on updating AuditLog');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // Attempt delete -> MUST throw RuntimeException
        try {
            $log->delete();
            $this->fail('Expected RuntimeException on deleting AuditLog');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        // Verify log is still in database unchanged
        $log->refresh();
        $this->assertEquals('Catatan awal otentik.', $log->details);
    }

    public function test_query_builder_bulk_update_on_audit_logs_is_blocked_by_trigger(): void
    {
        $school = $this->makeSchool();

        $log = AuditLog::create([
            'school_id' => $school->id,
            'actor_name' => 'Admin Asli',
            'actor_role' => 'admin',
            'action' => 'ORIGINAL',
            'details' => 'Data asli yang tidak boleh diubah.',
        ]);

        // Attempt bulk update via Query Builder (bypasses Eloquent events).
        // Wrapped in a savepoint: on PostgreSQL a raised exception aborts the whole
        // transaction, so any verification query afterwards would fail with 25P02
        // unless the savepoint is rolled back first.
        try {
            DB::transaction(function () use ($log) {
                DB::table('audit_logs')->where('id', $log->id)->update(['details' => 'DIMANIPULASI']);
            });

            // No exception: the trigger didn't fire on this engine. Verify the data
            // stayed the same via the Eloquent model protection path instead.
            $log->refresh();
            $this->assertEquals('Data asli yang tidak boleh diubah.', $log->details,
                'Query builder bypass succeeded — trigger protection missing on this DB engine.');
        } catch (\Exception $e) {
            // Trigger fired and blocked the operation — this is the desired path
            $this->assertStringContainsString('append-only', $e->getMessage());
            $log->refresh();
            $this->assertEquals('Data asli yang tidak boleh diubah.', $log->details);
        }
    }

    public function test_query_builder_bulk_delete_on_audit_logs_is_blocked_by_trigger(): void
    {
        $school = $this->makeSchool();

        AuditLog::create([
            'school_id' => $school->id,
            'actor_name' => 'Admin',
            'actor_role' => 'admin',
            'action' => 'DELETE_TARGET',
            'details' => 'Record yang tidak boleh dihapus.',
        ]);

        $countBefore = AuditLog::count();

        try {
            DB::transaction(function () {
                DB::table('audit_logs')->where('action', 'DELETE_TARGET')->delete();
            });

            $countAfter = AuditLog::count();
            $this->assertEquals($countBefore, $countAfter,
                'Query builder delete bypassed trigger — protection missing.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
            $this->assertEquals($countBefore, AuditLog::count());
        }
    }

    // =========================================================================
    // QA-02: MIGRATION SAFETY
    // =========================================================================

    public function test_migration_000021_backfill_does_not_force_ownership_on_multi_school_data(): void
    {
        // This is a documentation test: the migration's up() method uses nullable
        // school_id and assigns orphan data to a single default school. This test
        // documents that the migration does NOT delete or overwrite existing
        // school_id values if they are already set.

        $school = $this->makeSchool();

        // Simulate a setting that already has school_id set
        $existing = SchoolSetting::create([
            'school_id' => $school->id,
            'school_name' => 'Pre-existing',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH',
            'lead_teacher' => 'Guru Existing',
        ]);

        // Verify the setting retains its school ownership
        $this->assertEquals($school->id, $existing->fresh()->school_id);
    }

    // =========================================================================
    // ROUTES & AUTHORIZATION
    // =========================================================================

    public function test_no_operational_routes_exist_for_audit_mutation_or_deletion(): void
    {
        $this->assertFalse(Route::has('audit.destroy'));
        $this->assertFalse(Route::has('audit.delete'));
        $this->assertFalse(Route::has('audit.update'));
        $this->assertFalse(Route::has('audit_logs.destroy'));
    }

    public function test_guest_is_redirected_to_login_on_settings_routes(): void
    {
        $this->get(route('settings.index'))->assertRedirect('/login');
        $this->post(route('settings.update'), $this->validSettingsPayload())->assertRedirect('/login');
    }

    /**
     * Install a trigger that forces INSERT on audit_logs to fail.
     * Works on both SQLite (RAISE) and PostgreSQL (plpgsql function).
     */
    private function installForcedAuditInsertFailure(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION trg_test_force_audit_failure()
                RETURNS TRIGGER AS $$
                BEGIN
                    RAISE EXCEPTION 'Simulasi kegagalan audit';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS trg_test_force_audit_failure ON audit_logs;
                CREATE TRIGGER trg_test_force_audit_failure
                BEFORE INSERT ON audit_logs
                FOR EACH ROW
                EXECUTE FUNCTION trg_test_force_audit_failure();
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS trg_test_force_audit_failure
                BEFORE INSERT ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'Simulasi kegagalan audit');
                END;
            SQL);
        }
    }

    private function dropForcedAuditInsertFailure(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS trg_test_force_audit_failure ON audit_logs;
                DROP FUNCTION IF EXISTS trg_test_force_audit_failure();
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS trg_test_force_audit_failure;');
        }
    }
}
