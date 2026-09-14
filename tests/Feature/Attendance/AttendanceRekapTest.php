<?php

namespace Tests\Feature\Attendance;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceRekapTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function viewer(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('viewer');

        return $user;
    }

    public function test_viewer_can_access_the_rekap_page(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/attendance/rekap')->assertOk();
    }

    public function test_days_without_a_record_are_marked_belum_diisi(): void
    {
        $admin = $this->admin();
        $job = Job::factory()->create();
        $period = JobPeriod::factory()->create(['job_id' => $job->id]);
        $employee = Employee::factory()->create();

        $assignment = Assignment::factory()->create([
            'job_period_id' => $period->id,
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => '2025-09-03',
            'created_by' => $admin->id,
        ]);

        Attendance::factory()->create([
            'assignment_id' => $assignment->id,
            'tanggal' => '2025-09-02',
            'status' => 'hadir',
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(
            '/attendance/rekap?date_from=2025-09-01&date_to=2025-09-03',
            ['X-Inertia' => 'true', 'X-Inertia-Version' => file_exists($m = public_path('build/manifest.json')) ? hash_file('xxh128', $m) : '']
        );

        $response->assertOk();
        $response->assertJsonPath('props.rows.0.days.2025-09-01', 'belum_diisi');
        $response->assertJsonPath('props.rows.0.days.2025-09-02', 'hadir');
        $response->assertJsonPath('props.rows.0.days.2025-09-03', 'belum_diisi');
        $response->assertJsonPath('props.rows.0.summary.hadir', 1);
    }

    public function test_job_filter_narrows_the_results(): void
    {
        $admin = $this->admin();
        $jobA = Job::factory()->create();
        $jobB = Job::factory()->create();
        $periodA = JobPeriod::factory()->create(['job_id' => $jobA->id, 'tanggal_mulai' => '2025-09-01']);
        $periodB = JobPeriod::factory()->create(['job_id' => $jobB->id, 'tanggal_mulai' => '2025-09-01']);

        Assignment::factory()->create([
            'job_period_id' => $periodA->id,
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => '2025-09-02',
            'created_by' => $admin->id,
        ]);
        Assignment::factory()->create([
            'job_period_id' => $periodB->id,
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => '2025-09-02',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(
            "/attendance/rekap?date_from=2025-09-01&date_to=2025-09-02&job_id={$jobA->id}",
            ['X-Inertia' => 'true', 'X-Inertia-Version' => file_exists($m = public_path('build/manifest.json')) ? hash_file('xxh128', $m) : '']
        );

        $response->assertJsonCount(1, 'props.rows');
    }

    public function test_index_defaults_to_the_current_week_when_no_filters_given(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/attendance/rekap');

        $response->assertOk();
    }

    public function test_assignment_with_no_overlap_in_range_is_excluded(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->create();

        Assignment::factory()->create([
            'job_period_id' => $period->id,
            'tanggal_mulai' => '2025-01-01',
            'tanggal_selesai' => '2025-01-05',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(
            '/attendance/rekap?date_from=2025-09-01&date_to=2025-09-03',
            ['X-Inertia' => 'true', 'X-Inertia-Version' => file_exists($m = public_path('build/manifest.json')) ? hash_file('xxh128', $m) : '']
        );

        $response->assertJsonCount(0, 'props.rows');
    }

    public function test_days_after_today_are_not_marked_belum_diisi_even_when_tanggal_selesai_is_in_the_future(): void
    {
        Carbon::setTestNow('2025-09-03');

        $admin = $this->admin();
        $period = JobPeriod::factory()->create();

        // Assignment ends well after "today" (2025-09-03), inside the
        // requested filter range.
        Assignment::factory()->create([
            'job_period_id' => $period->id,
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => '2025-09-10',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(
            '/attendance/rekap?date_from=2025-09-01&date_to=2025-09-05',
            ['X-Inertia' => 'true', 'X-Inertia-Version' => file_exists($m = public_path('build/manifest.json')) ? hash_file('xxh128', $m) : '']
        );

        $response->assertOk();
        $response->assertJsonPath('props.rows.0.days.2025-09-01', 'belum_diisi');
        $response->assertJsonPath('props.rows.0.days.2025-09-03', 'belum_diisi');
        // 2025-09-04 and 2025-09-05 are after "today" (2025-09-03) and must
        // be absent entirely, not marked belum_diisi.
        $response->assertJsonMissingPath('props.rows.0.days.2025-09-04');
        $response->assertJsonMissingPath('props.rows.0.days.2025-09-05');

        Carbon::setTestNow();
    }

    public function test_malformed_date_from_returns_a_validation_error_instead_of_a_500(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/attendance/rekap?date_from=lol&date_to=2025-09-03');

        $response->assertSessionHasErrors('date_from');
    }

    public function test_date_to_before_date_from_is_rejected(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/attendance/rekap?date_from=2025-09-05&date_to=2025-09-01');

        $response->assertSessionHasErrors('date_to');
    }

    public function test_out_of_range_job_id_is_rejected(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(
            '/attendance/rekap?date_from=2025-09-01&date_to=2025-09-03&job_id=999999'
        );

        $response->assertSessionHasErrors('job_id');
    }

    public function test_too_wide_a_date_span_is_rejected(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(
            '/attendance/rekap?date_from=1900-01-01&date_to=2100-01-01'
        );

        $response->assertSessionHasErrors('date_to');
    }

    public function test_malformed_week_start_on_input_screen_returns_a_validation_error_instead_of_a_500(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/attendance/input?week_start=not-a-date');

        $response->assertSessionHasErrors('week_start');
    }
}
