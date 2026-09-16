<?php

namespace Tests\Feature\Admin;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
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

    public function test_admin_can_change_a_users_name_and_role(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Nama Lama']);
        $user->assignRole('viewer');

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'name' => 'Nama Baru',
            'role' => 'staff_input',
        ])->assertSessionHas('success', 'User berhasil diperbarui.')->assertRedirect('/users');

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
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
            'name' => $target->name,
            'role' => 'staff_input',
        ])->assertForbidden();

        $target->refresh();
        $this->assertTrue($target->hasRole('viewer'));
    }

    public function test_sole_admin_cannot_demote_themselves(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
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
            'name' => $secondAdmin->name,
            'role' => 'viewer',
        ])->assertSessionHas('success', 'User berhasil diperbarui.')->assertRedirect('/users');

        $secondAdmin->refresh();
        $this->assertTrue($secondAdmin->hasRole('viewer'));
        $this->assertFalse($secondAdmin->hasRole('admin'));
    }

    public function test_admin_can_view_user_detail(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Detail User']);
        $user->assignRole('viewer');

        $this->actingAs($admin)->get("/users/{$user->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Show')
                ->where('user.name', 'Detail User')
                ->where('user.role', 'viewer'));
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->actingAs($admin)->delete("/users/{$user->id}")
            ->assertSessionHas('success', 'User berhasil dihapus.')
            ->assertRedirect('/users');

        $this->assertNull(User::find($user->id));
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/users/{$admin->id}")
            ->assertSessionHas('error', 'Tidak bisa menghapus akun sendiri.');

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_non_admin_cannot_delete_a_user(): void
    {
        $admin = $this->admin();
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->delete("/users/{$admin->id}")->assertForbidden();

        $this->assertNotNull(User::find($admin->id));
    }

    public function test_user_cannot_be_deleted_when_referenced_by_assignment_history(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();
        $staff->assignRole('staff_input');

        $employee = Employee::factory()->create();
        $jobPeriod = JobPeriod::factory()->create();
        Assignment::factory()->create([
            'employee_id' => $employee->id,
            'job_period_id' => $jobPeriod->id,
            'created_by' => $staff->id,
        ]);

        $this->actingAs($admin)->delete("/users/{$staff->id}")
            ->assertSessionHas('error', 'User tidak bisa dihapus karena memiliki riwayat data (assignment/absensi).');

        $this->assertNotNull(User::find($staff->id));
    }

    public function test_admin_can_reset_another_users_password(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('viewer');
        $originalPassword = $user->password;

        $this->actingAs($admin)->put("/users/{$user->id}/reset-password", [
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertSessionHas('success', 'Password user berhasil direset.')->assertRedirect('/users');

        $user->refresh();
        $this->assertNotSame($originalPassword, $user->password);
    }

    public function test_reset_password_requires_confirmation_match(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->actingAs($admin)->put("/users/{$user->id}/reset-password", [
            'password' => 'password-baru-123',
            'password_confirmation' => 'tidak-cocok',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_can_search_users_by_name_or_email(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Findable Person', 'email' => 'findme@worktrack.test']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'else@worktrack.test']);

        $this->actingAs($admin)->get('/users?search=Findable')
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Findable Person'));
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $admin = $this->admin();
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');
        $staff = User::factory()->create();
        $staff->assignRole('staff_input');

        $this->actingAs($admin)->get('/users?role=staff_input')
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->has('users.data', 1)
                ->where('users.data.0.id', $staff->id));
    }
}
