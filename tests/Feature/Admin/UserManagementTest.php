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

        $response->assertSessionHas('success', 'User berhasil ditambahkan.')->assertRedirect('/users');

        $newUser = User::where('email', 'staff.baru@worktrack.test')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole('viewer'));
        $this->assertFalse($newUser->hasRole('staff_input'));
    }

    public function test_admin_can_create_user_with_explicit_staff_input_role(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Staff Input',
            'email' => 'staff.input@worktrack.test',
            'password' => 'password123',
            'role' => 'staff_input',
        ]);

        $response->assertSessionHas('success', 'User berhasil ditambahkan.');

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
        ])->assertSessionHas('success', 'Role user berhasil diperbarui.')->assertRedirect('/users');

        $user->refresh();
        $this->assertTrue($user->hasRole('staff_input'));
        $this->assertFalse($user->hasRole('viewer'));
    }

    public function test_staff_input_cannot_access_user_management(): void
    {
        $this->seed(RoleSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole('staff_input');

        $this->actingAs($staff)->get('/users')->assertForbidden();
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->post('/users', [
            'name' => 'Should Not Exist',
            'email' => 'should.not.exist@worktrack.test',
            'password' => 'password123',
            'role' => 'viewer',
        ])->assertForbidden();

        $this->assertNull(User::where('email', 'should.not.exist@worktrack.test')->first());
    }

    public function test_non_admin_cannot_update_a_users_role(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $target = User::factory()->create();
        $target->assignRole('viewer');

        $this->actingAs($viewer)->put("/users/{$target->id}", [
            'role' => 'staff_input',
        ])->assertForbidden();

        $target->refresh();
        $this->assertTrue($target->hasRole('viewer'));
    }

    public function test_sole_admin_cannot_demote_themselves(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->put("/users/{$admin->id}", [
            'role' => 'viewer',
        ]);

        $response->assertSessionHasErrors('role');
        $response->assertRedirect();

        $admin->refresh();
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertFalse($admin->hasRole('viewer'));
    }

    public function test_admin_can_change_another_admins_role_when_a_second_admin_exists(): void
    {
        $admin = $this->admin();
        $secondAdmin = User::factory()->create();
        $secondAdmin->assignRole('admin');

        $this->actingAs($admin)->put("/users/{$secondAdmin->id}", [
            'role' => 'viewer',
        ])->assertSessionHas('success', 'Role user berhasil diperbarui.')->assertRedirect('/users');

        $secondAdmin->refresh();
        $this->assertTrue($secondAdmin->hasRole('viewer'));
        $this->assertFalse($secondAdmin->hasRole('admin'));
    }
}
