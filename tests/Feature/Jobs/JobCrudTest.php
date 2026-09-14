<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobCrudTest extends TestCase
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

    public function test_viewer_can_see_the_job_list(): void
    {
        $viewer = $this->viewer();
        Job::factory()->create(['nama_pekerjaan' => 'Mesin 2']);

        $response = $this->actingAs($viewer)->get('/jobs', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'Jobs/Index');
        $response->assertJsonPath('props.jobs.data.0.nama_pekerjaan', 'Mesin 2');
        $response->assertJsonPath('props.canManage', false);
    }

    public function test_viewer_cannot_create_a_job(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/jobs/create')->assertForbidden();
        $this->actingAs($viewer)->post('/jobs', [])->assertForbidden();
    }

    public function test_admin_can_create_a_job_with_its_first_period(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/jobs', [
            'nama_pekerjaan' => 'Siaga Ubur Ubur',
            'lokasi' => 'Probolinggo',
            'klien' => 'PLN Unit X',
            'jenis_dokumen' => 'PR',
            'no_dokumen' => '27796',
            'kode_po' => 'PTC04E',
            'nilai_po' => 85969680,
            'tanggal_mulai' => '2025-05-17',
            'tanggal_selesai' => '2025-06-13',
            'jumlah_tk_rencana' => 31,
        ]);

        $response->assertSessionHas('success', 'Job berhasil ditambahkan.');
        $response->assertRedirect(route('jobs.index'));
        $this->assertDatabaseHas('client_jobs', ['nama_pekerjaan' => 'Siaga Ubur Ubur', 'status' => 'aktif']);
        $job = Job::where('nama_pekerjaan', 'Siaga Ubur Ubur')->firstOrFail();
        $this->assertDatabaseHas('job_periods', [
            'job_id' => $job->id,
            'no_dokumen' => '27796',
            'status' => 'aktif',
            'previous_period_id' => null,
        ]);
    }

    public function test_staff_input_can_create_a_job(): void
    {
        $staffInput = $this->staffInput();

        $response = $this->actingAs($staffInput)->post('/jobs', [
            'nama_pekerjaan' => 'Helper Gudang',
            'jenis_dokumen' => 'PR',
            'no_dokumen' => '27791',
            'nilai_po' => 6812604,
            'tanggal_mulai' => '2025-05-07',
            'tanggal_selesai' => '2025-05-20',
            'jumlah_tk_rencana' => 3,
        ]);

        $response->assertSessionHas('success', 'Job berhasil ditambahkan.');
        $response->assertRedirect(route('jobs.index'));
        $this->assertDatabaseHas('client_jobs', ['nama_pekerjaan' => 'Helper Gudang']);
    }

    public function test_no_dokumen_must_be_unique_across_all_document_types(): void
    {
        $admin = $this->admin();
        $existingJob = Job::factory()->create();
        JobPeriod::factory()->create(['job_id' => $existingJob->id, 'no_dokumen' => '27791']);

        $response = $this->actingAs($admin)->post('/jobs', [
            'nama_pekerjaan' => 'Job Lain',
            'jenis_dokumen' => 'DO',
            'no_dokumen' => '27791',
            'nilai_po' => 1000000,
            'tanggal_mulai' => '2025-05-07',
            'jumlah_tk_rencana' => 1,
        ]);

        $response->assertSessionHasErrors('no_dokumen');
    }

    public function test_tanggal_selesai_must_not_be_before_tanggal_mulai(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/jobs', [
            'nama_pekerjaan' => 'Job Tanggal Salah',
            'jenis_dokumen' => 'PR',
            'no_dokumen' => '99999',
            'nilai_po' => 1000000,
            'tanggal_mulai' => '2025-05-20',
            'tanggal_selesai' => '2025-05-01',
            'jumlah_tk_rencana' => 1,
        ]);

        $response->assertSessionHasErrors('tanggal_selesai');
    }

    public function test_show_page_lists_periods_newest_first_and_flags_active_period(): void
    {
        $admin = $this->admin();
        $job = Job::factory()->create();
        $older = JobPeriod::factory()->berakhir()->create([
            'job_id' => $job->id,
            'tanggal_mulai' => '2025-01-01',
        ]);
        $newer = JobPeriod::factory()->create([
            'job_id' => $job->id,
            'tanggal_mulai' => '2025-06-01',
            'previous_period_id' => $older->id,
        ]);

        $response = $this->actingAs($admin)->get("/jobs/{$job->id}", $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'Jobs/Show');
        $response->assertJsonPath('props.periods.0.id', $newer->id);
        $response->assertJsonPath('props.periods.1.id', $older->id);
        $response->assertJsonPath('props.hasActivePeriod', true);
    }

    public function test_viewer_cannot_toggle_job_status(): void
    {
        $viewer = $this->viewer();
        $job = Job::factory()->create();

        $this->actingAs($viewer)->patch("/jobs/{$job->id}/toggle-status")->assertForbidden();
    }

    public function test_admin_can_toggle_job_status(): void
    {
        $admin = $this->admin();
        $job = Job::factory()->create();

        $this->actingAs($admin)
            ->patch("/jobs/{$job->id}/toggle-status")
            ->assertSessionHas('success', 'Status job berhasil diperbarui.')
            ->assertRedirect(route('jobs.show', $job));

        $this->assertSame('selesai', $job->fresh()->status->value);

        $this->actingAs($admin)->patch("/jobs/{$job->id}/toggle-status");

        $this->assertSame('aktif', $job->fresh()->status->value);
    }

    public function test_search_filters_by_name_or_client(): void
    {
        $admin = $this->admin();
        Job::factory()->create(['nama_pekerjaan' => 'Mesin 2', 'klien' => 'PLN Unit A']);
        Job::factory()->create(['nama_pekerjaan' => 'Siaga Ubur Ubur', 'klien' => 'PLN Unit B']);

        $response = $this->actingAs($admin)->get('/jobs?search=Mesin', $this->inertiaHeaders());

        $response->assertJsonCount(1, 'props.jobs.data');
        $response->assertJsonPath('props.jobs.data.0.nama_pekerjaan', 'Mesin 2');
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        Job::factory()->create(['nama_pekerjaan' => 'Job Aktif']);
        Job::factory()->selesai()->create(['nama_pekerjaan' => 'Job Selesai']);

        $response = $this->actingAs($admin)->get('/jobs?status=selesai', $this->inertiaHeaders());

        $response->assertJsonCount(1, 'props.jobs.data');
        $response->assertJsonPath('props.jobs.data.0.nama_pekerjaan', 'Job Selesai');
    }
}
