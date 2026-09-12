<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Job;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_user_counts_per_role(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $viewers = User::factory()->count(2)->create();
        foreach ($viewers as $viewer) {
            $viewer->assignRole('viewer');
        }

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('roleCounts.admin', 1)
            ->where('roleCounts.staff_input', 0)
            ->where('roleCounts.viewer', 2)
            ->where('roleCounts.total', 3)
        );
    }

    public function test_dashboard_shows_employee_count(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Employee::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('employeeCount', 3)
        );
    }

    public function test_dashboard_shows_active_job_count(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Job::factory()->count(2)->create();
        Job::factory()->selesai()->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('jobCount', 2)
        );
    }

    public function test_dashboard_shows_active_assignment_count(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Assignment::factory()->count(2)->create(['created_by' => $admin->id]);
        Assignment::factory()->selesai()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('assignmentCount', 2)
        );
    }
}
