<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HandleInertiaRequestsRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_roles_are_shared_with_inertia(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('viewer');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.roles', ['viewer'])
        );
    }
}
