<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegacySubscriptionIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_checkout_routes_are_not_registered_or_exposed(): void
    {
        $this->assertFalse(Route::has('subscription.index'));
        $this->assertFalse(Route::has('subscription.checkout'));

        $this->get('/subscription')->assertNotFound();
        $this->post('/subscription/checkout')->assertNotFound();
    }

    public function test_sidebar_does_not_expose_a_subscription_navigation_link(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('href="/subscription"', false);
    }
}
