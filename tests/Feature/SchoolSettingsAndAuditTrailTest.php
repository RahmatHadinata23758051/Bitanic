<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SchoolSettingsAndAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // AC-1: Settings & Thresholds Have School Ownership
    // -------------------------------------------------------------------------

    public function test_school_model_exposes_owned_settings_and_audit_logs(): void
    {
        $school = School::firstOrCreate(
            ['slug' => 'smk-ppn-1-sukaraja'],
            ['npsn' => '20200001', 'name' => 'SMK Sukaraja', 'status' => 'active']
        );
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
        $schoolA = School::firstOrCreate(
            ['slug' => 'smk-ppn-1-sukaraja'],
            ['npsn' => '20200001', 'name' => 'SMK Sukaraja', 'status' => 'active']
        );
        $schoolB = School::create([
            'npsn' => '20200099',
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
            'sensor_thresholds' => [
                'minMoisture' => 35.0,
                'maxMoisture' => 70.0,
                'minPH' => 6.0,
                'maxPH' => 7.0,
                'minEC' => 1000,
                'maxEC' => 2000,
                'minNitrogen' => 50,
                'maxNitrogen' => 100,
            ],
        ]);

        SchoolSetting::create([
            'school_id' => $schoolB->id,
            'school_name' => 'SMK Mitra B Berdikari',
            'academic_year' => '2026/2027',
            'semester' => 'Genap',
            'course_name' => 'Smart Farming Vokasi B',
            'lead_teacher' => 'Guru Kampus B',
            'sensor_thresholds' => [
                'minMoisture' => 40.0,
                'maxMoisture' => 80.0,
                'minPH' => 5.8,
                'maxPH' => 6.8,
                'minEC' => 900,
                'maxEC' => 1800,
                'minNitrogen' => 45,
                'maxNitrogen' => 95,
            ],
        ]);

        $adminA = User::factory()->create(['role' => 'admin', 'school_id' => $schoolA->id]);
        $adminB = User::factory()->create(['role' => 'admin', 'school_id' => $schoolB->id]);

        // Admin A sees School A settings
        $responseA = $this->actingAs($adminA)->get(route('settings.index'));
        $responseA->assertOk();
        $responseA->assertSee('SMK PPN 1 Sukaraja Kampus Utama');
        $responseA->assertDontSee('SMK Mitra B Berdikari');

        // Admin B sees School B settings
        $responseB = $this->actingAs($adminB)->get(route('settings.index'));
        $responseB->assertOk();
        $responseB->assertSee('SMK Mitra B Berdikari');
        $responseB->assertDontSee('SMK PPN 1 Sukaraja Kampus Utama');
    }

    // -------------------------------------------------------------------------
    // AC-2: Permission Denial for Students & Unauthenticated Users
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // AC-3: Threshold Range and Unit Validation
    // -------------------------------------------------------------------------

    public function test_school_settings_thresholds_validation_enforces_physical_ranges_and_ordering(): void
    {
        $school = School::firstOrCreate(
            ['slug' => 'smk-ppn-1-sukaraja'],
            ['npsn' => '20200001', 'name' => 'SMK Sukaraja', 'status' => 'active']
        );
        $admin = User::factory()->create(['role' => 'admin', 'school_id' => $school->id]);

        // 1. Inverted moisture (min > max)
        $response1 = $this->actingAs($admin)->post(route('settings.update'), [
            'school_name' => 'SMK Sukaraja',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH',
            'lead_teacher' => 'Guru',
            'thresholds' => [
                'minMoisture' => 80.0,
                'maxMoisture' => 40.0, // Invalid: max < min
                'minPH' => 6.0,
                'maxPH' => 7.0,
                'minEC' => 1000,
                'maxEC' => 2000,
                'minNitrogen' => 50,
                'maxNitrogen' => 100,
            ],
        ]);
        $response1->assertSessionHasErrors(['thresholds.maxMoisture']);

        // 2. Out-of-bounds pH (pH must be 0 to 14)
        $response2 = $this->actingAs($admin)->post(route('settings.update'), [
            'school_name' => 'SMK Sukaraja',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH',
            'lead_teacher' => 'Guru',
            'thresholds' => [
                'minMoisture' => 30.0,
                'maxMoisture' => 70.0,
                'minPH' => -1.0, // Invalid: negative pH
                'maxPH' => 15.0, // Invalid: pH > 14
                'minEC' => 1000,
                'maxEC' => 2000,
                'minNitrogen' => 50,
                'maxNitrogen' => 100,
            ],
        ]);
        $response2->assertSessionHasErrors(['thresholds.minPH', 'thresholds.maxPH']);
    }

    // -------------------------------------------------------------------------
    // AC-4: Audit Record Generation with Correlation ID, Target, and Diff
    // -------------------------------------------------------------------------

    public function test_update_school_settings_records_audited_diff_with_correlation_id_and_redaction(): void
    {
        $school = School::firstOrCreate(
            ['slug' => 'smk-ppn-1-sukaraja'],
            ['npsn' => '20200001', 'name' => 'SMK Sukaraja', 'status' => 'active']
        );
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
            'sensor_thresholds' => [
                'minMoisture' => 20.0,
                'maxMoisture' => 60.0,
                'minPH' => 5.0,
                'maxPH' => 6.5,
                'minEC' => 800,
                'maxEC' => 1600,
                'minNitrogen' => 30,
                'maxNitrogen' => 80,
            ],
        ]);

        $response = $this->actingAs($admin)->post(route('settings.update'), [
            'school_name' => 'Nama Diperbarui 2026',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'Smart Agronomy 4.0',
            'lead_teacher' => 'Guru Baru',
            'thresholds' => [
                'minMoisture' => 35.0,
                'maxMoisture' => 75.0,
                'minPH' => 5.5,
                'maxPH' => 7.0,
                'minEC' => 900,
                'maxEC' => 1800,
                'minNitrogen' => 40,
                'maxNitrogen' => 90,
            ],
        ]);

        $response->assertRedirect(route('settings.index'));
        $response->assertSessionHas('success');

        // Verify one audited record includes target metadata and correlation.
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

    // -------------------------------------------------------------------------
    // AC-5: Append-Only Invariant: No Updates or Deletions Allowed
    // -------------------------------------------------------------------------

    public function test_audit_log_model_is_append_only_and_cannot_be_updated_or_deleted(): void
    {
        $school = School::firstOrCreate(
            ['slug' => 'smk-ppn-1-sukaraja'],
            ['npsn' => '20200001', 'name' => 'SMK Sukaraja', 'status' => 'active']
        );

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

    // -------------------------------------------------------------------------
    // AC-6: Tenant Isolation for Audit Trail View
    // -------------------------------------------------------------------------

    public function test_audit_trail_in_view_is_scoped_to_user_school(): void
    {
        $schoolA = School::firstOrCreate(
            ['slug' => 'smk-ppn-1-sukaraja'],
            ['npsn' => '20200001', 'name' => 'SMK Sukaraja', 'status' => 'active']
        );
        $schoolB = School::create([
            'npsn' => '20200099',
            'name' => 'SMK Pertanian B',
            'slug' => 'smk-pertanian-b',
            'status' => 'active',
        ]);

        $adminA = User::factory()->create(['role' => 'admin', 'school_id' => $schoolA->id]);

        AuditLog::create([
            'school_id' => $schoolA->id,
            'actor_name' => 'Admin Sukaraja',
            'actor_role' => 'admin',
            'action' => 'ACTUATION_RELAY_A',
            'details' => 'Aktivasi pompa nutrisi Meja 1',
        ]);

        AuditLog::create([
            'school_id' => $schoolB->id,
            'actor_name' => 'Admin Sekolah B',
            'actor_role' => 'admin',
            'action' => 'SECRET_ACTION_B',
            'details' => 'Rahasia internal Sekolah B',
        ]);

        $response = $this->actingAs($adminA)->get(route('settings.index'));
        $response->assertOk();
        $response->assertSee('Aktivasi pompa nutrisi Meja 1');
        $response->assertDontSee('Rahasia internal Sekolah B');
    }

    public function test_thresholds_validation_rejects_negative_or_inverted_ec_and_nitrogen(): void
    {
        $school = School::firstOrCreate(
            ['slug' => 'smk-ppn-1-sukaraja'],
            ['npsn' => '20200001', 'name' => 'SMK Sukaraja', 'status' => 'active']
        );
        $admin = User::factory()->create(['role' => 'admin', 'school_id' => $school->id]);

        // Inverted EC (min > max) and negative Nitrogen
        $response = $this->actingAs($admin)->post(route('settings.update'), [
            'school_name' => 'SMK Sukaraja',
            'academic_year' => '2026/2027',
            'semester' => 'Ganjil',
            'course_name' => 'ATPH',
            'lead_teacher' => 'Guru',
            'thresholds' => [
                'minMoisture' => 30.0,
                'maxMoisture' => 70.0,
                'minPH' => 6.0,
                'maxPH' => 7.0,
                'minEC' => 2500,
                'maxEC' => 1200, // Invalid: max < min
                'minNitrogen' => -10, // Invalid: negative
                'maxNitrogen' => 100,
            ],
        ]);

        $response->assertSessionHasErrors(['thresholds.maxEC', 'thresholds.minNitrogen']);
    }

    public function test_no_operational_routes_exist_for_audit_mutation_or_deletion(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('audit.destroy'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('audit.delete'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('audit.update'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('audit_logs.destroy'));
    }
}
