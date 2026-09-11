<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RootRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_login_page_at_root(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_authenticated_user_is_redirected_from_root_to_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_form_still_posts_to_root(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
