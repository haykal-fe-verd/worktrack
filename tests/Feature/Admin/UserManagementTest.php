<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get('/users')->assertForbidden();
    }

    public function test_admin_can_create_user_with_default_viewer_role(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Staff Baru',
            'email' => 'staff.baru@worktrack.test',
            'password' => 'password123',
            'role' => 'viewer',
        ]);

        $response->assertRedirect('/users');

        $newUser = User::where('email', 'staff.baru@worktrack.test')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole('viewer'));
        $this->assertFalse($newUser->hasRole('staff_input'));
    }

    public function test_admin_can_create_user_with_explicit_staff_input_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Staff Input',
            'email' => 'staff.input@worktrack.test',
            'password' => 'password123',
            'role' => 'staff_input',
        ]);

        $newUser = User::where('email', 'staff.input@worktrack.test')->first();
        $this->assertTrue($newUser->hasRole('staff_input'));
        $this->assertFalse($newUser->hasRole('viewer'));
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'role' => 'staff_input',
        ])->assertRedirect('/users');

        $user->refresh();
        $this->assertTrue($user->hasRole('staff_input'));
        $this->assertFalse($user->hasRole('viewer'));
    }
}
