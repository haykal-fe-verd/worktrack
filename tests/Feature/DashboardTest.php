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

    public function test_dashboard_shows_canmanage_and_isadmin_flags_for_admin(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)
            ->where('isAdmin', true)
        );
    }

    public function test_dashboard_shows_canmanage_true_and_isadmin_false_for_staff_input(): void
    {
        $this->seed(RoleSeeder::class);

        $staffInput = User::factory()->create();
        $staffInput->assignRole('staff_input');

        $response = $this->actingAs($staffInput)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)
            ->where('isAdmin', false)
        );
    }

    public function test_dashboard_shows_canmanage_and_isadmin_false_for_viewer(): void
    {
        $this->seed(RoleSeeder::class);

        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $response = $this->actingAs($viewer)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('canManage', false)
            ->where('isAdmin', false)
        );
    }

    public function test_dashboard_limits_recent_employees_and_jobs_to_five(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Employee::factory()->create(['nama' => 'Karyawan Terbaru']);
        Employee::factory()->count(5)->create();
        Job::factory()->create(['nama_pekerjaan' => 'Job Terbaru']);
        Job::factory()->count(5)->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('recentEmployees', 5)
            ->has('recentJobs', 5)
            ->where('recentEmployees.0.nama', 'Karyawan Terbaru')
            ->where('recentJobs.0.nama_pekerjaan', 'Job Terbaru')
        );
    }

    public function test_dashboard_shows_job_periods_ending_within_the_next_14_days(): void
    {
        Carbon::setTestNow('2025-09-03');

        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $job = Job::factory()->create(['nama_pekerjaan' => 'Job Segera Berakhir']);
        JobPeriod::factory()->create([
            'job_id' => $job->id,
            'status' => JobPeriodStatus::Aktif,
            'tanggal_selesai' => now()->addDays(5)->format('Y-m-d'),
        ]);

        // Too far in the future — excluded.
        JobPeriod::factory()->create([
            'status' => JobPeriodStatus::Aktif,
            'tanggal_selesai' => now()->addDays(30)->format('Y-m-d'),
        ]);

        // Already ended — excluded.
        JobPeriod::factory()->create([
            'status' => JobPeriodStatus::Aktif,
            'tanggal_selesai' => now()->subDays(2)->format('Y-m-d'),
        ]);

        // No tanggal_selesai — excluded.
        JobPeriod::factory()->create([
            'status' => JobPeriodStatus::Aktif,
            'tanggal_selesai' => null,
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('upcomingJobPeriods', 1)
            ->where('upcomingJobPeriods.0.job_nama_pekerjaan', 'Job Segera Berakhir')
        );

        Carbon::setTestNow();
    }

    public function test_dashboard_shows_attendance_summary_counts_for_the_current_week(): void
    {
        Carbon::setTestNow('2025-09-03');

        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $weekStart = now()->startOfWeek(Carbon::MONDAY);

        $period = JobPeriod::factory()->create(['status' => JobPeriodStatus::Aktif]);
        $assignment = Assignment::factory()->create([
            'job_period_id' => $period->id,
            'status' => AssignmentStatus::Aktif,
            'is_current' => true,
            'tanggal_mulai' => $weekStart->format('Y-m-d'),
            'tanggal_selesai' => $weekStart->copy()->addDays(2)->format('Y-m-d'),
            'created_by' => $admin->id,
        ]);

        Attendance::factory()->create([
            'assignment_id' => $assignment->id,
            'tanggal' => $weekStart->format('Y-m-d'),
            'status' => 'hadir',
            'recorded_by' => $admin->id,
        ]);
        Attendance::factory()->create([
            'assignment_id' => $assignment->id,
            'tanggal' => $weekStart->copy()->addDay()->format('Y-m-d'),
            'status' => 'izin',
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('attendanceSummary.hadir', 1)
            ->where('attendanceSummary.tidak_hadir', 0)
            ->where('attendanceSummary.izin', 1)
            ->where('attendanceSummary.belum_diisi', 1)
        );

        Carbon::setTestNow();
    }
}
