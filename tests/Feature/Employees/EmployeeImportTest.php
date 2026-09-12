<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
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

    private function makeXlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['NAMA', 'NIK', 'ALAMAT', 'NO REKENING'], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'karyawan.xlsx', null, null, true);
    }

    public function test_viewer_cannot_access_import(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get('/employees/import')->assertForbidden();
    }

    public function test_viewer_cannot_submit_or_download_import(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->post('/employees/import', [])->assertForbidden();
        $this->actingAs($viewer)->get('/employees/import/errors')->assertForbidden();
    }

    public function test_staff_input_can_access_import(): void
    {
        $staffInput = $this->staffInput();

        $this->actingAs($staffInput)->get('/employees/import')->assertOk();
    }

    public function test_valid_rows_are_imported(): void
    {
        $admin = $this->admin();
        $file = $this->makeXlsx([
            ['Budi Santoso', '3513126804000001', 'Jl. A', '1923973699'],
            ['Siti Aminah', '3512161807980001', 'Jl. B', '1184150367'],
        ]);

        $response = $this->actingAs($admin)->post('/employees/import', ['file' => $file]);

        $response->assertRedirect(route('employees.import.create'));
        $response->assertSessionHas('employee_import_result', fn ($result) => $result['created'] === 2
            && $result['skipped'] === 0
            && $result['errorCount'] === 0);
        $response->assertSessionHas('success', 'Import selesai: 2 berhasil, 0 dilewati, 0 gagal.');

        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseHas('employees', ['nik' => '3513126804000001', 'nama' => 'Budi Santoso']);
    }

    public function test_duplicate_nik_with_same_name_is_skipped_not_errored(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '3513126804000001', 'nama' => 'Budi Santoso']);

        $file = $this->makeXlsx([
            ['Budi Santoso', '3513126804000001', 'Jl. A', '1923973699'],
        ]);

        $response = $this->actingAs($admin)->post('/employees/import', ['file' => $file]);

        $response->assertSessionHas('employee_import_result', fn ($result) => $result['created'] === 0
            && $result['skipped'] === 1
            && $result['errorCount'] === 0);

        $this->assertDatabaseCount('employees', 1);
    }

    public function test_duplicate_nik_with_different_name_is_an_error(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '3513126804000001', 'nama' => 'Budi Santoso']);

        $file = $this->makeXlsx([
            ['Nama Berbeda', '3513126804000001', 'Jl. A', '1923973699'],
        ]);

        $response = $this->actingAs($admin)->post('/employees/import', ['file' => $file]);

        $response->assertSessionHas('employee_import_result', fn ($result) => $result['errorCount'] === 1);
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_malformed_nik_is_an_error(): void
    {
        $admin = $this->admin();
        $file = $this->makeXlsx([
            ['Budi Santoso', '12345', 'Jl. A', '1923973699'],
        ]);

        $response = $this->actingAs($admin)->post('/employees/import', ['file' => $file]);

        $response->assertSessionHas('employee_import_result', fn ($result) => $result['errorCount'] === 1);
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_error_report_can_be_downloaded_once_then_is_gone(): void
    {
        $admin = $this->admin();
        $file = $this->makeXlsx([
            ['Budi Santoso', '12345', 'Jl. A', '1923973699'],
        ]);

        $this->actingAs($admin)->post('/employees/import', ['file' => $file]);

        $this->actingAs($admin)
            ->get('/employees/import/errors')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($admin)->get('/employees/import/errors')->assertNotFound();
    }

    public function test_downloading_errors_with_none_pending_returns_not_found(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/employees/import/errors')->assertNotFound();
    }

    public function test_a_clean_import_clears_a_previous_error_report(): void
    {
        $admin = $this->admin();

        $badFile = $this->makeXlsx([
            ['Budi Santoso', '12345', 'Jl. A', '1923973699'],
        ]);
        $this->actingAs($admin)->post('/employees/import', ['file' => $badFile]);

        // Deliberately do NOT download the error report here — this is the
        // scenario that exposes the bug: a stale report sitting unclaimed
        // in the session when a subsequent clean import runs.

        $goodFile = $this->makeXlsx([
            ['Siti Aminah', '3512161807980001', 'Jl. B', '1184150367'],
        ]);
        $this->actingAs($admin)->post('/employees/import', ['file' => $goodFile]);

        $this->actingAs($admin)->get('/employees/import/errors')->assertNotFound();
    }
}
