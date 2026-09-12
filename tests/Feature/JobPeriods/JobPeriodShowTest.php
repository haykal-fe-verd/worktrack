<?php

namespace Tests\Feature\JobPeriods;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPeriodShowTest extends TestCase
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

    public function test_page_renders_with_assignments_and_no_warning_when_counts_match(): void
    {
        $admin = $this->admin();
        $job = Job::factory()->create(['nama_pekerjaan' => 'Mesin 2']);
        $period = JobPeriod::factory()->create(['job_id' => $job->id, 'jumlah_tk_rencana' => 1]);
        $employee = Employee::factory()->create(['nama' => 'Budi']);
        Assignment::factory()->create([
            'job_period_id' => $period->id,
            'employee_id' => $employee->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/job-periods/{$period->id}", $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'JobPeriods/Show');
        $response->assertJsonPath('props.jobPeriod.job_nama_pekerjaan', 'Mesin 2');
        $response->assertJsonPath('props.assignments.0.employee_nama', 'Budi');
        $response->assertJsonPath('props.activeAssignmentCount', 1);
        $response->assertJsonPath('props.warningJumlahTk', false);
    }

    public function test_warning_shows_when_active_count_differs_from_planned(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->create(['jumlah_tk_rencana' => 5]);

        $response = $this->actingAs($admin)->get("/job-periods/{$period->id}", $this->inertiaHeaders());

        $response->assertJsonPath('props.warningJumlahTk', true);
        $response->assertJsonPath('props.activeAssignmentCount', 0);
    }

    public function test_viewer_sees_masked_tarif(): void
    {
        $viewer = $this->viewer();
        $period = JobPeriod::factory()->create();
        Assignment::factory()->create([
            'job_period_id' => $period->id,
            'tarif_jual' => 1500000,
        ]);

        $response = $this->actingAs($viewer)->get("/job-periods/{$period->id}", $this->inertiaHeaders());

        $response->assertOk();
        $tarif = $response->json('props.assignments.0.tarif_jual');
        $this->assertNotSame('1500000.00', $tarif);
    }
}
