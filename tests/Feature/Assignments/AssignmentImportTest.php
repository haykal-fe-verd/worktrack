<?php

namespace Tests\Feature\Assignments;

use App\Enums\AssignmentStatus;
use App\Enums\EmployeeStatus;
use App\Enums\JobPeriodStatus;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AssignmentImportTest extends TestCase
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
            ['NIK', 'NO_DOKUMEN', 'TANGGAL_MULAI', 'TANGGAL_SELESAI', 'TARIF_JUAL', 'TARIF_BAYAR'],
            null,
            'A1',
        );

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'assignments.xlsx', null, null, true);
    }

    public function test_viewer_cannot_access_import(): void
    {
        $this->actingAs($this->viewer())->get('/assignments/import')->assertForbidden();
    }

    public function test_staff_input_can_access_import(): void
    {
        $this->actingAs($this->staffInput())->get('/assignments/import')->assertOk();
    }

    public function test_valid_row_creates_an_active_assignment_when_tanggal_selesai_is_empty(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456']);
        $jobPeriod = JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '2025-01-01', '', '', ''],
        ]);

        $response = $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $response->assertRedirect(route('assignments.import.create'));
        $response->assertSessionHas('assignment_import_result', fn ($result) => $result['created'] === 1 && $result['errorCount'] === 0);
        $response->assertSessionHas('success', 'Import selesai: 1 berhasil, 0 gagal.');

        $assignment = Assignment::where('job_period_id', $jobPeriod->id)->firstOrFail();
        $this->assertSame(AssignmentStatus::Aktif, $assignment->status);
        $this->assertTrue($assignment->is_current);
        $this->assertNull($assignment->previous_assignment_id);
        $this->assertNull($assignment->tanggal_selesai);
    }

    public function test_tanggal_selesai_in_the_past_produces_selesai_status_but_stays_current(): void
    {
        Carbon::setTestNow('2025-06-15');

        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456']);
        JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '2025-01-01', '2025-02-01', '', ''],
        ]);

        $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $assignment = Assignment::firstOrFail();
        $this->assertSame(AssignmentStatus::Selesai, $assignment->status);
        $this->assertTrue($assignment->is_current);

        Carbon::setTestNow();
    }

    public function test_tanggal_selesai_in_the_future_produces_aktif_status(): void
    {
        Carbon::setTestNow('2025-01-15');

        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456']);
        JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '2025-01-01', '2025-12-31', '', ''],
        ]);

        $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $assignment = Assignment::firstOrFail();
        $this->assertSame(AssignmentStatus::Aktif, $assignment->status);

        Carbon::setTestNow();
    }

    public function test_nik_not_16_digits_is_an_error(): void
    {
        $admin = $this->admin();
        JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);

        $file = $this->makeXlsx([
            ['123', 'PR-001', '2025-01-01', '', '', ''],
        ]);

        $response = $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $response->assertSessionHas('assignment_import_result', fn ($result) => $result['errorCount'] === 1);
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_nik_not_found_is_an_error(): void
    {
        $admin = $this->admin();
        JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '2025-01-01', '', '', ''],
        ]);

        $response = $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $response->assertSessionHas('assignment_import_result', fn ($result) => $result['errorCount'] === 1);
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_no_dokumen_not_found_is_an_error(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-999', '2025-01-01', '', '', ''],
        ]);

        $response = $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $response->assertSessionHas('assignment_import_result', fn ($result) => $result['errorCount'] === 1);
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_missing_tanggal_mulai_is_an_error(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456']);
        JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '', '', '', ''],
        ]);

        $response = $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $response->assertSessionHas('assignment_import_result', fn ($result) => $result['errorCount'] === 1);
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_tanggal_selesai_before_tanggal_mulai_is_an_error(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456']);
        JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '2025-06-01', '2025-01-01', '', ''],
        ]);

        $response = $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $response->assertSessionHas('assignment_import_result', fn ($result) => $result['errorCount'] === 1);
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_duplicate_employee_and_job_period_combination_is_an_error(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['nik' => '1234567890123456']);
        $jobPeriod = JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);
        Assignment::factory()->create([
            'employee_id' => $employee->id,
            'job_period_id' => $jobPeriod->id,
            'created_by' => $admin->id,
        ]);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '2025-01-01', '', '', ''],
        ]);

        $response = $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $response->assertSessionHas('assignment_import_result', fn ($result) => $result['errorCount'] === 1);
        $this->assertDatabaseCount('assignments', 1);
    }

    public function test_empty_tarif_fields_are_stored_as_null(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456']);
        JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '2025-01-01', '', '', ''],
        ]);

        $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $assignment = Assignment::firstOrFail();
        $this->assertNull($assignment->tarif_jual);
        $this->assertNull($assignment->tarif_bayar);
    }

    public function test_indonesian_formatted_tarif_is_parsed_correctly(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456']);
        JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '2025-01-01', '', '1.234.567,89', '1.900.000'],
        ]);

        $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $assignment = Assignment::firstOrFail();
        $this->assertEqualsWithDelta(1234567.89, (float) $assignment->tarif_jual, 0.001);
        $this->assertEqualsWithDelta(1900000.0, (float) $assignment->tarif_bayar, 0.001);
    }

    public function test_import_succeeds_for_a_job_period_regardless_of_status(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456']);
        JobPeriod::factory()->create(['no_dokumen' => 'PR-001', 'status' => JobPeriodStatus::Berakhir]);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '2025-01-01', '', '', ''],
        ]);

        $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $this->assertDatabaseCount('assignments', 1);
    }

    public function test_import_succeeds_for_a_nonaktif_employee(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456', 'status' => EmployeeStatus::NonAktif]);
        JobPeriod::factory()->create(['no_dokumen' => 'PR-001']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-001', '2025-01-01', '', '', ''],
        ]);

        $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $this->assertDatabaseCount('assignments', 1);
    }

    public function test_error_report_can_be_downloaded_once_then_is_gone(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '1234567890123456']);

        $file = $this->makeXlsx([
            ['1234567890123456', 'PR-999', '2025-01-01', '', '', ''],
        ]);

        $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $this->actingAs($admin)
            ->get('/assignments/import/errors')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)->get('/assignments/import/errors')->assertNotFound();
    }

    public function test_download_errors_returns_404_when_no_errors_stored(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/assignments/import/errors')->assertNotFound();
    }
}
