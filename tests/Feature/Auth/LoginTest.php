<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Portal Laboratorium Vokasi IoT');
        $response->assertSee('BITANIC LMS');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'email' => 'guru@smk.id',
            'password' => Hash::make('password123'),
            'role' => 'guru',
        ]);

        $response = $this->post('/login', [
            'email' => 'guru@smk.id',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('schedules.index'));
    }

    public function test_student_is_redirected_to_modules_after_login(): void
    {
        $student = User::factory()->create([
            'email' => 'siswa@smk.id',
            'password' => Hash::make('password123'),
            'role' => 'siswa',
        ]);

        $response = $this->post('/login', [
            'email' => 'siswa@smk.id',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('modules.index'));
    }

    public function test_admin_is_redirected_to_dashboard_after_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@smk.id',
            'password' => Hash::make('password123'),
            'role' => 'superuser',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@smk.id',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'guru@smk.id',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'guru@smk.id',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_login_attempts_are_throttled_after_too_many_failures(): void
    {
        $user = User::factory()->create([
            'email' => 'siswa@smk.id',
            'password' => Hash::make('password123'),
        ]);

        RateLimiter::clear('login:siswa@smk.id|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'siswa@smk.id',
                'password' => 'wrong-password',
            ]);
        }

        // Attempt ke-6 harus di-throttle
        $response = $this->post('/login', [
            'email' => 'siswa@smk.id',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        $this->assertStringContainsString('Terlalu banyak percobaan login', $errors[0]);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }
}
