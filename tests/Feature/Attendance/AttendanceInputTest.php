<?php

namespace Tests\Feature\Attendance;

use App\Enums\AssignmentStatus;
use App\Enums\JobPeriodStatus;
use App\Enums\JobStatus;
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

class AttendanceInputTest extends TestCase
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

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        $version = file_exists($manifest = public_path('build/manifest.json'))
            ? hash_file('xxh128', $manifest)
            : '';

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
        ];
    }

    public function test_viewer_cannot_access_input_page_or_submit(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/attendance/input')->assertForbidden();
        $this->actingAs($viewer)->post('/attendance/input', [])->assertForbidden();
    }

    public function test_job_without_active_period_shows_no_grid(): void
    {
        $admin = $this->admin();
        $job = Job::factory()->create(['status' => JobStatus::Aktif]);

        $response = $this->actingAs($admin)->get("/attendance/input?job_id={$job->id}", $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('props.hasActivePeriod', false);
        $response->assertJsonPath('props.rows', []);
    }

    public function test_grid_shows_current_assignments_with_correct_disabled_cells(): void
    {
        // Freeze "today" to a fixed Thursday so that Wednesday of the
        // current week (day index 2) is guaranteed to be in the past,
        // regardless of which real-world weekday the suite runs on.
        $this->travelTo(Carbon::create(2025, 1, 9));

        $admin = $this->admin();
        $job = Job::factory()->create(['status' => JobStatus::Aktif]);
        $period = JobPeriod::factory()->create(['job_id' => $job->id, 'status' => JobPeriodStatus::Aktif]);
        $employee = Employee::factory()->create(['nama' => 'Budi']);

        $weekStart = now()->startOfWeek(Carbon::MONDAY);

        $assignment = Assignment::factory()->create([
            'job_period_id' => $period->id,
            'employee_id' => $employee->id,
            'is_current' => true,
            'status' => AssignmentStatus::Aktif,
            'tanggal_mulai' => $weekStart->copy()->addDays(2)->format('Y-m-d'),
            'tanggal_selesai' => null,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(
            "/attendance/input?job_id={$job->id}&week_start={$weekStart->format('Y-m-d')}",
            $this->inertiaHeaders()
        );

        $response->assertOk();
        $response->assertJsonPath('props.hasActivePeriod', true);
        $response->assertJsonPath('props.rows.0.assignment_id', $assignment->id);
        $response->assertJsonPath('props.rows.0.employee_nama', 'Budi');
        // Day 0 (Monday) is before tanggal_mulai (Wednesday) — disabled
        $response->assertJsonPath('props.rows.0.cells.0.disabled', true);
        // Day 2 (Wednesday) is tanggal_mulai itself — enabled
        $response->assertJsonPath('props.rows.0.cells.2.disabled', false);
    }

    public function test_store_creates_a_new_attendance_record(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->create([
            'status' => AssignmentStatus::Aktif,
            'tanggal_mulai' => now()->subDays(2)->format('Y-m-d'),
            'tanggal_selesai' => null,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post('/attendance/input', [
            'assignment_id' => $assignment->id,
            'tanggal' => now()->subDay()->format('Y-m-d'),
            'status' => 'hadir',
            'catatan' => null,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success', 'Absensi berhasil disimpan.');

        $this->assertDatabaseHas('attendances', [
            'assignment_id' => $assignment->id,
            'status' => 'hadir',
        ]);
    }

    public function test_store_upserts_an_existing_record_instead_of_duplicating(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->create([
            'status' => AssignmentStatus::Aktif,
            'tanggal_mulai' => now()->subDays(2)->format('Y-m-d'),
            'tanggal_selesai' => null,
            'created_by' => $admin->id,
        ]);
        $tanggal = now()->subDay()->format('Y-m-d');

        Attendance::factory()->create([
            'assignment_id' => $assignment->id,
            'tanggal' => $tanggal,
            'status' => 'hadir',
            'recorded_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post('/attendance/input', [
            'assignment_id' => $assignment->id,
            'tanggal' => $tanggal,
            'status' => 'izin',
            'catatan' => 'Sakit',
        ]);

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'assignment_id' => $assignment->id,
            'status' => 'izin',
            'catatan' => 'Sakit',
        ]);
    }

    public function test_store_rejects_a_date_outside_the_assignment_range(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->create([
            'status' => AssignmentStatus::Aktif,
            'tanggal_mulai' => now()->subDays(2)->format('Y-m-d'),
            'tanggal_selesai' => now()->subDay()->format('Y-m-d'),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post('/attendance/input', [
            'assignment_id' => $assignment->id,
            'tanggal' => now()->format('Y-m-d'),
            'status' => 'hadir',
        ]);

        $response->assertSessionHasErrors('tanggal');
    }

    public function test_store_rejects_a_non_active_assignment(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->selesai()->create([
            'tanggal_mulai' => now()->subDays(5)->format('Y-m-d'),
            'tanggal_selesai' => now()->subDays(2)->format('Y-m-d'),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post('/attendance/input', [
            'assignment_id' => $assignment->id,
            'tanggal' => now()->subDays(3)->format('Y-m-d'),
            'status' => 'hadir',
        ]);

        $response->assertSessionHasErrors('assignment_id');
    }
}
