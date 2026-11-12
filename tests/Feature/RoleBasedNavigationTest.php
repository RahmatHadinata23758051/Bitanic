<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create([
            'npsn' => '20207777',
            'name' => 'SMK Negeri 1 Pertanian Navigasi',
            'slug' => 'smk-negeri-1-pertanian-navigasi',
            'city' => 'Bogor',
            'province' => 'Jawa Barat',
            'status' => 'active',
        ]);
    }

    public function test_siswa_sidebar_is_clean_learning_portal_without_administrative_clutter(): void
    {
        $siswa = User::factory()->create([
            'role' => 'siswa',
            'school_id' => $this->school->id,
        ]);

        $response = $this->actingAs($siswa)->get(url('/'));

        $response->assertOk();

        // Learning items present
        $response->assertSee('Dashboard');
        $response->assertSee('Monitoring IoT');
        $response->assertSee('Modul Praktikum');
        $response->assertSee('Jadwal Praktikum');
        $response->assertSee('LKS Saya');
        $response->assertSee('Rapor Saya');

        // Administrative / Teacher-only items absent from Siswa sidebar
        $response->assertDontSee('href="'.url('/analytics').'"', false);
        $response->assertDontSee('href="'.url('/telemetry/history').'"', false);
        $response->assertDontSee('href="'.url('/media').'"', false);
        $response->assertDontSee('href="'.url('/trainer-kits').'"', false);
        $response->assertDontSee('href="'.url('/raw-logs').'"', false);
        $response->assertDontSee('href="'.url('/users-data').'"', false);
        $response->assertDontSee('href="'.url('/settings').'"', false);
        $response->assertDontSee('Fleet Trainer Kit');
        $response->assertDontSee('Sekolah Mitra');
    }

    public function test_guru_sidebar_has_teaching_and_grading_tools(): void
    {
        $guru = User::factory()->create([
            'role' => 'guru',
            'school_id' => $this->school->id,
        ]);

        $response = $this->actingAs($guru)->get(url('/'));

        $response->assertOk();

        // Operational teaching items
        $response->assertSee('Dashboard');
        $response->assertSee('Monitoring IoT');
        $response->assertSee('Media Tanam');
        $response->assertSee('Analitik Agronomi');
        $response->assertSee('Riwayat Telemetri');
        $response->assertSee('Modul Praktikum');
        $response->assertSee('Jadwal Praktikum');
        $response->assertSee('Kelas');
        $response->assertSee('Laporan LKS');
        $response->assertSee('Rapor Kompetensi');
        $response->assertSee('Trainer Kit');
        $response->assertSee('Debugger RS485');
        $response->assertSee('Direktori');

        // Superuser-only macro items absent
        $response->assertDontSee('Dashboard Pusat');
        $response->assertDontSee('Sekolah Mitra');
        $response->assertDontSee('Fleet Trainer Kit');
    }

    public function test_admin_sekolah_sidebar_includes_license_and_institutional_management(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin_sekolah',
            'school_id' => $this->school->id,
        ]);

        $response = $this->actingAs($admin)->get(url('/'));

        $response->assertOk();

        $response->assertSee('Administrasi Sekolah');
        $response->assertSee('Status Lisensi');
        $response->assertSee(route('school.license.show'));
        $response->assertSee('Trainer Kit');
        $response->assertSee('Direktori');
        $response->assertSee('Pengaturan');
    }

    public function test_superuser_sidebar_is_macro_platform_view_without_single_school_operations(): void
    {
        $superuser = User::factory()->create([
            'role' => 'superuser',
        ]);

        $response = $this->actingAs($superuser)->get(url('/'));

        $response->assertOk();

        // Macro platform navigation
        $response->assertSee('Platform Pusat');
        $response->assertSee('Dashboard Pusat');
        $response->assertSee('Sekolah Mitra');
        $response->assertSee('Wilayah &amp; Peta', false);
        $response->assertSee('Lisensi LMS');
        $response->assertSee('Fleet Trainer Kit');
        $response->assertSee('Debugger RS485');
        $response->assertSee('Pengaturan &amp; Audit', false);

        // Single-school operational items absent from primary sidebar
        $response->assertDontSee('Jadwal Praktikum');
        $response->assertDontSee('Laporan LKS');
        $response->assertDontSee('Rapor Kompetensi');
    }
}
