<?php

namespace Tests\Feature\Jobs;

use App\Enums\JobPeriodStatus;
use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPeriodRenewalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function staffInput(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('staff_input');

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

    public function test_viewer_cannot_access_the_renew_picker_or_submit_a_continuation(): void
    {
        $viewer = $this->viewer();
        $job = Job::factory()->create();

        $this->actingAs($viewer)->get("/jobs/{$job->id}/renew")->assertForbidden();
        $this->actingAs($viewer)->get("/jobs/{$job->id}/periods/create")->assertForbidden();
        $this->actingAs($viewer)->post("/jobs/{$job->id}/periods", [])->assertForbidden();
    }

    public function test_renew_picker_page_renders_for_a_job_with_an_active_period(): void
    {
        $admin = $this->admin();
        $job = Job::factory()->create(['nama_pekerjaan' => 'Mesin 2']);
        JobPeriod::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($admin)->get("/jobs/{$job->id}/renew", $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'Jobs/Renew');
        $response->assertJsonPath('props.job.nama_pekerjaan', 'Mesin 2');
    }

    public function test_scenario_a_continuation_links_to_previous_period_and_closes_it(): void
    {
        $admin = $this->admin();
        $job = Job::factory()->create();
        $oldPeriod = JobPeriod::factory()->create(['job_id' => $job->id, 'no_dokumen' => '035767']);

        $response = $this->actingAs($admin)->post("/jobs/{$job->id}/periods", [
            'jenis_dokumen' => 'PR',
            'no_dokumen' => '035768',
            'nilai_po' => 50000000,
            'tanggal_mulai' => '2025-08-01',
            'jumlah_tk_rencana' => 10,
        ]);

        $response->assertRedirect(route('jobs.show', $job));

        $newPeriod = JobPeriod::where('no_dokumen', '035768')->firstOrFail();
        $this->assertSame($oldPeriod->id, $newPeriod->previous_period_id);
        $this->assertSame(JobPeriodStatus::Aktif, $newPeriod->status);
        $this->assertSame(JobPeriodStatus::Berakhir, $oldPeriod->fresh()->status);
        $this->assertSame($job->id, $newPeriod->job_id);
    }

    public function test_scenario_a_form_page_renders(): void
    {
        $admin = $this->admin();
        $job = Job::factory()->create(['nama_pekerjaan' => 'Mesin 2']);

        $response = $this->actingAs($admin)->get("/jobs/{$job->id}/periods/create", $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'Jobs/Periods/Create');
        $response->assertJsonPath('props.job.nama_pekerjaan', 'Mesin 2');
    }

    public function test_new_period_no_dokumen_must_be_unique(): void
    {
        $admin = $this->admin();
        $job = Job::factory()->create();
        JobPeriod::factory()->create(['job_id' => $job->id, 'no_dokumen' => '035767']);

        $anotherJob = Job::factory()->create();
        JobPeriod::factory()->create(['job_id' => $anotherJob->id, 'no_dokumen' => '999999']);

        $response = $this->actingAs($admin)->post("/jobs/{$anotherJob->id}/periods", [
            'jenis_dokumen' => 'PR',
            'no_dokumen' => '035767',
            'nilai_po' => 1000000,
            'tanggal_mulai' => '2025-08-01',
            'jumlah_tk_rencana' => 1,
        ]);

        $response->assertSessionHasErrors('no_dokumen');
    }

    public function test_staff_input_can_access_the_renew_picker_and_submit_a_continuation(): void
    {
        $staffInput = $this->staffInput();
        $job = Job::factory()->create();
        $oldPeriod = JobPeriod::factory()->create(['job_id' => $job->id, 'no_dokumen' => 'staff-input-old']);

        $this->actingAs($staffInput)->get("/jobs/{$job->id}/renew")->assertOk();

        $response = $this->actingAs($staffInput)->post("/jobs/{$job->id}/periods", [
            'jenis_dokumen' => 'PR',
            'no_dokumen' => 'staff-input-new',
            'nilai_po' => 1000000,
            'tanggal_mulai' => '2025-09-01',
            'jumlah_tk_rencana' => 5,
        ]);

        $response->assertRedirect(route('jobs.show', $job));
        $this->assertSame($oldPeriod->id, JobPeriod::where('no_dokumen', 'staff-input-new')->firstOrFail()->previous_period_id);
    }
}
