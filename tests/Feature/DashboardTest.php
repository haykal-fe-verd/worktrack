<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\JobPeriodStatus;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_dashboard_shows_belum_diisi_count_for_the_current_week(): void
    {
        // Freeze "today" to mid-week so both days of the assignment below
        // (Monday and Tuesday) are in the past relative to "today" — the
        // dashboard's belum-diisi count never counts days after today.
        Carbon::setTestNow('2025-09-03');

        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $weekStart = now()->startOfWeek(Carbon::MONDAY);

        $period = JobPeriod::factory()->create();
        $assignment = Assignment::factory()->create([
            'job_period_id' => $period->id,
            'tanggal_mulai' => $weekStart->format('Y-m-d'),
            'tanggal_selesai' => $weekStart->copy()->addDays(1)->format('Y-m-d'),
            'created_by' => $admin->id,
        ]);

        // Only Monday has a record; Tuesday (the assignment's other day) is belum diisi.
        Attendance::factory()->create([
            'assignment_id' => $assignment->id,
            'tanggal' => $weekStart->format('Y-m-d'),
            'status' => 'hadir',
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('belumDiisiCount', 1)
        );

        Carbon::setTestNow();
    }

    public function test_belum_diisi_count_excludes_assignments_that_cannot_be_filled_from_input_absensi(): void
    {
        // Freeze "today" to mid-week so both days of each assignment below
        // are in the past relative to "today".
        Carbon::setTestNow('2025-09-03');

        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $weekStart = now()->startOfWeek(Carbon::MONDAY);

        // Superseded assignment (is_current = false, Diperbarui) under an
        // active JobPeriod: this can never be filled from Input Absensi
        // (AttendanceController::input only queries is_current assignments).
        $supersededPeriod = JobPeriod::factory()->create(['status' => JobPeriodStatus::Aktif]);
        Assignment::factory()->create([
            'job_period_id' => $supersededPeriod->id,
            'tanggal_mulai' => $weekStart->format('Y-m-d'),
            'tanggal_selesai' => $weekStart->copy()->addDays(1)->format('Y-m-d'),
            'status' => AssignmentStatus::Diperbarui,
            'is_current' => false,
            'created_by' => $admin->id,
        ]);

        // Eligible assignment: is_current, Aktif, under an active JobPeriod.
        $activePeriod = JobPeriod::factory()->create(['status' => JobPeriodStatus::Aktif]);
        Assignment::factory()->create([
            'job_period_id' => $activePeriod->id,
            'tanggal_mulai' => $weekStart->format('Y-m-d'),
            'tanggal_selesai' => $weekStart->copy()->addDays(1)->format('Y-m-d'),
            'status' => AssignmentStatus::Aktif,
            'is_current' => true,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('belumDiisiCount', 2)
        );

        Carbon::setTestNow();
    }
}
