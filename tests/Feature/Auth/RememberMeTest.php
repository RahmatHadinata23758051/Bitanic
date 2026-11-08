<?php

namespace Tests\Feature\Auth;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * BR-602: functional QA of real Laravel "Ingat saya" (remember me) persistence.
 *
 * Every assertion here maps to observable framework behaviour:
 * - the recaller cookie name is `remember_web_<sha1(SessionGuard::class)>`
 * - its value is `userId|remember_token|HMAC(passwordHash)`, encrypted by the
 *   EncryptCookies middleware before it reaches the browser
 * - `users.remember_token` is the persistent server-side credential
 * - `Auth::guard('web')->viaRemember()` reports recaller-based authentication
 */
class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    private function recallerName(): string
    {
        return Auth::guard('web')->getRecallerName();
    }

    /**
     * Factory users ship with a random remember_token, so pin it to null to make
     * "was a persistent credential issued?" an unambiguous question.
     */
    private function guru(string $email = 'guru@smk.id'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'guru',
            'must_change_password' => false,
            'remember_token' => null,
        ]);
    }

    /**
     * Replay a Set-Cookie value the way a real browser would: the wire value is
     * already encrypted, so it must be sent unencrypted-by-the-test-client.
     */
    private function replayRecaller(string $wireValue): static
    {
        return $this->withUnencryptedCookie($this->recallerName(), $wireValue);
    }

    public function test_checked_login_issues_remember_cookie(): void
    {
        $this->guru();

        $response = $this->post('/login', [
            'email' => 'guru@smk.id',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('schedules.index'));
        $response->assertCookie($this->recallerName());
        $this->assertNotNull(User::where('email', 'guru@smk.id')->value('remember_token'));
    }

    public function test_unchecked_login_does_not_issue_remember_cookie(): void
    {
        $user = $this->guru();

        $response = $this->post('/login', [
            'email' => 'guru@smk.id',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertCookieMissing($this->recallerName());
        $this->assertNull($user->fresh()->remember_token);
        $this->assertFalse(Auth::guard('web')->viaRemember());
    }

    public function test_retained_remember_cookie_reauthenticates_after_session_expiry(): void
    {
        $user = $this->guru();

        $login = $this->post('/login', [
            'email' => 'guru@smk.id',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $wireRecaller = $login->getCookie($this->recallerName(), false)->getValue();
        $this->assertNotEmpty($wireRecaller);

        // Browser restart: the session cookie is gone, the persistent cookie is not.
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->replayRecaller($wireRecaller)
            ->get(route('reports.index'))
            ->assertOk();

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertTrue(Auth::guard('web')->viaRemember(), 'Access must be attributed to the remember-me cookie.');
    }

    public function test_logout_invalidates_remember_cookie_and_persistent_credential(): void
    {
        $user = $this->guru();

        $login = $this->post('/login', [
            'email' => 'guru@smk.id',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $wireRecaller = $login->getCookie($this->recallerName(), false)->getValue();
        $issuedToken = $user->fresh()->remember_token;
        $this->assertNotNull($issuedToken);

        $logout = $this->post('/logout');

        $this->assertGuest();
        $logout->assertCookieMissing($this->recallerName());

        // Laravel cycles the persistent credential to a new random value on logout,
        // which is what makes the retained cookie unusable.
        $this->assertNotSame($issuedToken, $user->fresh()->remember_token);

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->replayRecaller($wireRecaller)
            ->get(route('reports.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_invalid_credentials_do_not_issue_remember_cookie(): void
    {
        $user = $this->guru();

        $response = $this->from('/login')->post('/login', [
            'email' => 'guru@smk.id',
            'password' => 'wrong-password',
            'remember' => '1',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $response->assertCookieMissing($this->recallerName());
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_throttled_login_does_not_issue_remember_cookie(): void
    {
        $user = $this->guru();
        RateLimiter::clear('login:guru@smk.id|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'guru@smk.id',
                'password' => 'wrong-password',
                'remember' => '1',
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'email' => 'guru@smk.id',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $response->assertCookieMissing($this->recallerName());
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_inactive_school_login_does_not_issue_remember_cookie(): void
    {
        $user = $this->guru('inactive.guru@smk.id');
        $user->school->update(['status' => 'inactive']);

        $response = $this->from('/login')->post('/login', [
            'email' => 'inactive.guru@smk.id',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $response->assertCookieMissing($this->recallerName());
        $this->assertFalse(Auth::guard('web')->viaRemember());
    }

    public function test_remembered_user_whose_school_is_deactivated_loses_access(): void
    {
        $user = $this->guru();

        $login = $this->post('/login', [
            'email' => 'guru@smk.id',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $wireRecaller = $login->getCookie($this->recallerName(), false)->getValue();

        School::where('id', $user->school_id)->update(['status' => 'inactive']);

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // Remember-me restores the session, but school-scoped access stays fail-closed.
        $this->replayRecaller($wireRecaller)
            ->get(route('dashboard'))
            ->assertStatus(403);
    }

    public function test_remembered_user_keeps_role_scoped_authorization(): void
    {
        $this->guru();

        $login = $this->post('/login', [
            'email' => 'guru@smk.id',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $wireRecaller = $login->getCookie($this->recallerName(), false)->getValue();

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // A remembered guru still reaches teacher pages and is still barred from admin pages.
        $this->replayRecaller($wireRecaller)->get(route('schedules.index'))->assertOk();
        $this->assertSame('guru', Auth::user()->role);
        $this->replayRecaller($wireRecaller)->get(route('settings.index'))->assertStatus(403);
    }

    public function test_remembered_user_still_faces_first_password_change_gate(): void
    {
        $user = $this->guru('mustchange@smk.id');
        $user->forceFill(['must_change_password' => true])->save();

        $login = $this->post('/login', [
            'email' => 'mustchange@smk.id',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $wireRecaller = $login->getCookie($this->recallerName(), false)->getValue();
        $login->assertRedirect(route('password.first_change'));

        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // Remember-me restores the session but must not bypass the forced password change.
        $this->replayRecaller($wireRecaller)
            ->get(route('schedules.index'))
            ->assertRedirect(route('password.first_change'));
    }
}
