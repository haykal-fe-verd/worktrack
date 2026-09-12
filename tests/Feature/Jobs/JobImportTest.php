<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class JobImportTest extends TestCase
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
     * @param  array<int, array<int, string>>  $rows
     */
    private function makeXlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(
            ['NO. DO/PR/WO', 'URAIAN PEKERJAAN', 'JUMLAH TK', 'MULAI TANGGAL', 'S/D TANGGAL', 'PO', 'NILAI PO', 'KETERANGAN'],
            null,
            'A1',
        );

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'jobs.xlsx', null, null, true);
    }

    public function test_viewer_cannot_access_import(): void
    {
        $this->actingAs($this->viewer())->get('/jobs/import')->assertForbidden();
    }

    public function test_staff_input_can_access_import(): void
    {
        $this->actingAs($this->staffInput())->get('/jobs/import')->assertOk();
    }

    public function test_valid_rows_are_imported_as_independent_jobs(): void
    {
        $admin = $this->admin();
        $file = $this->makeXlsx([
            ['PR: 27791 DO: 001', 'Helper Gudang', '3 Org', '2025-05-07', '2025-05-20', 'PTC03E', '6.812.604', ''],
            ['PR: 27796', 'Siaga Ubur Ubur', '31 Org', '2025-05-17', '2025-06-13', 'PTC04E', '85.969.680', ''],
        ]);

        $response = $this->actingAs($admin)->post('/jobs/import', ['file' => $file]);

        $response->assertRedirect(route('jobs.import.create'));
        $response->assertSessionHas('job_import_result', fn ($result) => $result['created'] === 2 && $result['errorCount'] === 0);

        $this->assertDatabaseCount('client_jobs', 2);
        $this->assertDatabaseHas('client_jobs', ['nama_pekerjaan' => 'Helper Gudang']);

        $period = JobPeriod::where('no_dokumen', '27791')->firstOrFail();
        $this->assertSame('PR', $period->jenis_dokumen->value);
        $this->assertSame(3, $period->jumlah_tk_rencana);
        $this->assertEquals(6812604.0, (float) $period->nilai_po);
        $this->assertSame('2025-05-07', $period->tanggal_mulai->format('Y-m-d'));
        $this->assertSame('2025-05-20', $period->tanggal_selesai->format('Y-m-d'));
        $this->assertSame('PTC03E', $period->kode_po);

        $secondJob = Job::where('nama_pekerjaan', 'Siaga Ubur Ubur')->firstOrFail();
        $this->assertDatabaseHas('job_periods', ['job_id' => $secondJob->id, 'no_dokumen' => '27796']);
    }

    public function test_document_parsing_prefers_pr_over_do_when_both_present(): void
    {
        $admin = $this->admin();
        $file = $this->makeXlsx([
            ['PR: 27791 DO: 001', 'Helper Gudang', '3 Org', '2025-05-07', '2025-05-20', 'PTC03E', '6.812.604', ''],
        ]);

        $this->actingAs($admin)->post('/jobs/import', ['file' => $file]);

        $period = JobPeriod::where('no_dokumen', '27791')->firstOrFail();
        $this->assertSame('PR', $period->jenis_dokumen->value);
    }

    public function test_row_with_no_recognizable_document_pattern_is_an_error(): void
    {
        $admin = $this->admin();
        $file = $this->makeXlsx([
            ['tidak ada pola', 'Helper Gudang', '3 Org', '2025-05-07', '2025-05-20', 'PTC03E', '6.812.604', ''],
        ]);

        $response = $this->actingAs($admin)->post('/jobs/import', ['file' => $file]);

        $response->assertSessionHas('job_import_result', fn ($result) => $result['errorCount'] === 1);
        $this->assertDatabaseCount('client_jobs', 0);
    }

    public function test_duplicate_no_dokumen_is_an_error(): void
    {
        $admin = $this->admin();
        $existingJob = Job::factory()->create();
        JobPeriod::factory()->create(['job_id' => $existingJob->id, 'no_dokumen' => '27791']);

        $file = $this->makeXlsx([
            ['PR: 27791', 'Helper Gudang Lagi', '3 Org', '2025-05-07', '2025-05-20', 'PTC03E', '6.812.604', ''],
        ]);

        $response = $this->actingAs($admin)->post('/jobs/import', ['file' => $file]);

        $response->assertSessionHas('job_import_result', fn ($result) => $result['errorCount'] === 1);
        $this->assertDatabaseCount('client_jobs', 1);
    }

    public function test_error_report_can_be_downloaded_once_then_is_gone(): void
    {
        $admin = $this->admin();
        $file = $this->makeXlsx([
            ['tidak ada pola', 'Helper Gudang', '3 Org', '2025-05-07', '2025-05-20', 'PTC03E', '6.812.604', ''],
        ]);

        $this->actingAs($admin)->post('/jobs/import', ['file' => $file]);

        $this->actingAs($admin)
            ->get('/jobs/import/errors')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)->get('/jobs/import/errors')->assertNotFound();
    }
}
