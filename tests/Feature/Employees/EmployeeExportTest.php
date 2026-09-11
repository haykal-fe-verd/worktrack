<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_export(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get('/employees/export')->assertForbidden();
    }

    public function test_admin_can_export_unmasked_data(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Employee::factory()->create(['nik' => '3513126804000001']);

        $this->actingAs($admin)
            ->get('/employees/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_staff_input_can_export(): void
    {
        $this->seed(RoleSeeder::class);
        $staffInput = User::factory()->create();
        $staffInput->assignRole('staff_input');
        Employee::factory()->create(['nik' => '3513126804000001']);

        $this->actingAs($staffInput)
            ->get('/employees/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
