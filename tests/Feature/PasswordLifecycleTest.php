<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_must_change_password_is_redirected_after_login(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Bitanic@Initial123'),
            'must_change_password' => true,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Bitanic@Initial123',
        ]);

        $response->assertRedirect(route('password.first_change'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_with_must_change_password_cannot_access_other_web_pages_until_changed(): void
    {
        $user = User::factory()->create([
            'role' => 'siswa',
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->get(route('modules.index'));
        $response->assertRedirect(route('password.first_change'));
        $response->assertSessionHas('warning');
    }

    public function test_json_request_is_blocked_with_403_when_must_change_password_is_true(): void
    {
        $user = User::factory()->create([
            'role' => 'guru',
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('classes.store'), [
            'name' => 'XII ATPH 3',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('must_change_password', true);
    }

    public function test_user_can_successfully_update_initial_password_to_new_strong_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Bitanic@Initial123'),
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->post(route('password.first_change.update'), [
            'current_password' => 'Bitanic@Initial123',
            'password' => 'BitanicP@ssw0rd2026!Secured',
            'password_confirmation' => 'BitanicP@ssw0rd2026!Secured',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('BitanicP@ssw0rd2026!Secured', $user->password));

        // Now user can access protected pages freely
        $allowedResponse = $this->actingAs($user)->get(route('dashboard'));
        $allowedResponse->assertOk();
    }

    public function test_update_initial_password_fails_if_current_password_is_wrong_or_rules_violated(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Bitanic@Initial123'),
            'must_change_password' => true,
        ]);

        // Wrong current password
        $response = $this->actingAs($user)->post(route('password.first_change.update'), [
            'current_password' => 'WrongPassword',
            'password' => 'BitanicP@ssw0rd2026!Secured',
            'password_confirmation' => 'BitanicP@ssw0rd2026!Secured',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $user->refresh();
        $this->assertTrue($user->must_change_password);

        // Weak password (too short or missing symbols)
        $weakResponse = $this->actingAs($user)->post(route('password.first_change.update'), [
            'current_password' => 'Bitanic@Initial123',
            'password' => 'simple123',
            'password_confirmation' => 'simple123',
        ]);

        $weakResponse->assertSessionHasErrors(['password']);
        $user->refresh();
        $this->assertTrue($user->must_change_password);
    }
}
