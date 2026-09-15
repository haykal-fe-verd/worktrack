# Import Excel Assignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bulk-import `Assignment` records from an Excel file, matching each row's Employee via NIK and JobPeriod via No. Dokumen, following the exact pattern already established by the Employee and Job Excel imports.

**Architecture:** A new `App\Imports\AssignmentsImport` class (implements `ToCollection`, `WithHeadingRow`) does per-row matching/validation/creation, mirroring `JobsImport`'s structure. A new `App\Http\Controllers\AssignmentImportController` (create/store/downloadErrors) mirrors `JobImportController` exactly, reusing the existing generic `App\Exports\ImportErrorsExport`. Both are tested together at the HTTP boundary with real generated `.xlsx` files, matching this codebase's established convention (`tests/Feature/Jobs/JobImportTest.php`, `tests/Feature/Employees/EmployeeImportTest.php` — neither import class has its own separate unit test, both are only exercised through the controller). A new `Assignments/Import.tsx` page (shadcn `Sheet`, mirrors `Employees/Import.tsx`) is reached from a new "Import Penugasan" button on `Jobs/Index.tsx`, alongside the existing Job import/export buttons.

**Tech Stack:** Laravel 13 (PHP 8.4), Inertia.js + React 18 + TypeScript, `maatwebsite/excel` + `phpoffice/phpspreadsheet` (both already installed, no new packages).

**Spec:** [docs/superpowers/specs/2026-09-15-assignment-import-design.md](../specs/2026-09-15-assignment-import-design.md)

## Global Constraints

- No new composer or npm packages — `maatwebsite/excel`, `phpoffice/phpspreadsheet`, and the existing `App\Exports\ImportErrorsExport` class are reused as-is.
- Excel columns (heading row, lowercased+underscored by `WithHeadingRow`): `nik`, `no_dokumen`, `tanggal_mulai`, `tanggal_selesai`, `tarif_jual`, `tarif_bayar`. The raw header row text is `NIK, NO_DOKUMEN, TANGGAL_MULAI, TANGGAL_SELESAI, TARIF_JUAL, TARIF_BAYAR`.
- Route `role:admin|staff_input` gates all three new routes (create/store/downloadErrors) — write-gating consistent with manual Assignment CRUD.
- Validation order per row (first failure wins, row recorded to `$errors` and skipped, loop continues): NIK format/lookup → No. Dokumen lookup → Tanggal Mulai → Tanggal Selesai → duplicate check. Tarif parsing never fails (defaults to `null`), so it doesn't block a row.
- NIK must be 16 digits (same regex as `EmployeesImport::validateRow()`: `/^\d{16}$/`). Employee status is NOT validated (spec decision B5) — `App\Enums\EmployeeStatus` has cases `Aktif` and `NonAktif` (value `non_aktif`); both are accepted.
- JobPeriod status is NOT validated — any No. Dokumen match succeeds regardless of JobPeriod status (spec decision B4). `App\Enums\JobPeriodStatus` has cases `Aktif` and `Berakhir` (NOT `Selesai` — confirm this against `app/Enums/JobPeriodStatus.php` before writing any test, this plan's tests were written against it).
- Duplicate check: `Assignment::where('employee_id', ...)->where('job_period_id', ...)->exists()` — no `status`/`is_current` filter, checks the full history (spec decision B3).
- Status/`is_current` derivation: `tanggal_selesai` empty OR `>= today` → `AssignmentStatus::Aktif` + `is_current = true`; `tanggal_selesai < today` → `AssignmentStatus::Selesai` + `is_current = true` (spec decision B2). `App\Enums\AssignmentStatus` has cases `Aktif`, `Selesai`, `Diperbarui` — imported rows only ever use `Aktif` or `Selesai`, never `Diperbarui`.
- `previous_assignment_id` is always `null` for imported rows.
- `created_by` = the importing user's id (`$request->user()->id`, passed into the import class's constructor).
- Money parsing: Indonesian format (`.` = thousands separator, `,` = decimal separator) — reuse the exact algorithm from `JobsImport::parseIndonesianNumber()`.
- Date parsing: accept both a `DateTimeInterface` (Excel native date cell) and a string, matching `JobsImport::parseDate()` exactly.
- Error report headings: `NIK, NO. DOKUMEN, TANGGAL MULAI, TANGGAL SELESAI, TARIF JUAL, TARIF BAYAR, Alasan Gagal` — raw row values, not parsed/normalized ones.
- Flash messages: `errorCount === 0` → `"Import selesai: {created} berhasil, {errorCount} gagal."`; `errorCount > 0` → `"Import selesai dengan {errorCount} baris gagal ({created} berhasil). Lihat laporan error untuk detail."` (exact wording, matching `JobImportController::store()`).
- Session keys: `assignment_import_result` (flash data shown on the Import page) and `assignment_import_errors` (raw error rows, cleared after download).
- Upload validation: `['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120']]` (identical to Employee/Job import).
- Testing convention for this codebase's imports: ONE feature test class per import, in `tests/Feature/<PluralEntity>/<Entity>ImportTest.php`, testing exclusively through the HTTP endpoints with real `.xlsx` files built via `PhpOffice\PhpSpreadsheet\Spreadsheet`/`Xlsx` writer (see `tests/Feature/Jobs/JobImportTest.php` for the exact `makeXlsx()` helper to copy). There is no separate unit test file for the `*Import` class itself.
- Run `vendor/bin/pint --dirty --format agent` after any PHP change, `npx tsc --noEmit` after any TypeScript change, and `php artisan test --compact` before every commit.
- Every commit ends with the trailer `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`.

---

### Task 1: `AssignmentsImport` class, `AssignmentImportController`, routes, and feature tests

**Files:**
- Create: `app/Imports/AssignmentsImport.php`
- Create: `app/Http/Controllers/AssignmentImportController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Assignments/AssignmentImportTest.php`

**Interfaces:**
- Consumes: `App\Models\Employee` (`nik` field), `App\Models\JobPeriod` (`no_dokumen` field), `App\Models\Assignment` (fields `employee_id`, `job_period_id`, `tanggal_mulai`, `tanggal_selesai`, `status`, `is_current`, `tarif_jual`, `tarif_bayar`, `previous_assignment_id`, `created_by`), `App\Enums\AssignmentStatus` (`Aktif`, `Selesai`), `App\Exports\ImportErrorsExport` (existing, unchanged — constructed as `new ImportErrorsExport(collect($errors), $headings)`).
- Produces: `App\Imports\AssignmentsImport`, constructed as `new AssignmentsImport($userId)` where `$userId` is an `int`. Public properties after `Excel::import($import, $file)` runs: `array $errors` (list of associative arrays keyed by the exact heading strings in Global Constraints), `int $created`. Routes `assignments.import.create` (GET `/assignments/import`), `assignments.import.store` (POST `/assignments/import`), `assignments.import.errors` (GET `/assignments/import/errors`). Inertia component `Assignments/Import` rendered with prop `result: {created: number, errorCount: number} | null`.

- [ ] **Step 1: Write the `AssignmentsImport` class**

```php
<?php

namespace App\Imports;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

class AssignmentsImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, string>> */
    public array $errors = [];

    public int $created = 0;

    public function __construct(
        private readonly int $userId,
    ) {}

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $nik = trim((string) ($row['nik'] ?? ''));
            $noDokumen = trim((string) ($row['no_dokumen'] ?? ''));
            $tanggalMulaiRaw = $row['tanggal_mulai'] ?? null;
            $tanggalSelesaiRaw = $row['tanggal_selesai'] ?? null;
            $tarifJualRaw = trim((string) ($row['tarif_jual'] ?? ''));
            $tarifBayarRaw = trim((string) ($row['tarif_bayar'] ?? ''));

            if (! preg_match('/^\d{16}$/', $nik)) {
                $this->recordError($row, 'NIK harus 16 digit angka');

                continue;
            }

            $employee = Employee::where('nik', $nik)->first();

            if (! $employee) {
                $this->recordError($row, 'NIK tidak terdaftar');

                continue;
            }

            if ($noDokumen === '') {
                $this->recordError($row, 'No. Dokumen wajib diisi');

                continue;
            }

            $jobPeriod = JobPeriod::where('no_dokumen', $noDokumen)->first();

            if (! $jobPeriod) {
                $this->recordError($row, 'No. Dokumen tidak terdaftar');

                continue;
            }

            $tanggalMulai = $this->parseDate($tanggalMulaiRaw);

            if ($tanggalMulai === null) {
                $this->recordError($row, 'Tanggal Mulai wajib diisi dengan format tanggal yang valid');

                continue;
            }

            $tanggalSelesai = null;

            if ($tanggalSelesaiRaw !== null && trim((string) $tanggalSelesaiRaw) !== '') {
                $tanggalSelesai = $this->parseDate($tanggalSelesaiRaw);

                if ($tanggalSelesai === null) {
                    $this->recordError($row, 'Tanggal Selesai format tidak valid');

                    continue;
                }

                if (Carbon::parse($tanggalSelesai)->lt(Carbon::parse($tanggalMulai))) {
                    $this->recordError($row, 'Tanggal Selesai harus setelah atau sama dengan Tanggal Mulai');

                    continue;
                }
            }

            $exists = Assignment::where('employee_id', $employee->id)
                ->where('job_period_id', $jobPeriod->id)
                ->exists();

            if ($exists) {
                $this->recordError($row, 'Karyawan sudah punya penugasan pada periode ini');

                continue;
            }

            $isPastEnd = $tanggalSelesai !== null
                && Carbon::parse($tanggalSelesai)->lt(Carbon::today());

            Assignment::create([
                'employee_id' => $employee->id,
                'job_period_id' => $jobPeriod->id,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'status' => $isPastEnd ? AssignmentStatus::Selesai : AssignmentStatus::Aktif,
                'is_current' => true,
                'previous_assignment_id' => null,
                'tarif_jual' => $this->parseAmount($tarifJualRaw),
                'tarif_bayar' => $this->parseAmount($tarifBayarRaw),
                'created_by' => $this->userId,
            ]);

            $this->created++;
        }
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function parseAmount(string $value): ?float
    {
        if ($value === '') {
            return null;
        }

        $normalized = str_replace('.', '', $value);
        $normalized = str_replace(',', '.', $normalized);

        return (float) preg_replace('/[^0-9.\-]/', '', $normalized);
    }

    /**
     * @param  Collection<string, mixed>  $row
     */
    private function recordError(Collection $row, string $reason): void
    {
        $this->errors[] = [
            'NIK' => (string) ($row['nik'] ?? ''),
            'NO. DOKUMEN' => (string) ($row['no_dokumen'] ?? ''),
            'TANGGAL MULAI' => (string) ($row['tanggal_mulai'] ?? ''),
            'TANGGAL SELESAI' => (string) ($row['tanggal_selesai'] ?? ''),
            'TARIF JUAL' => (string) ($row['tarif_jual'] ?? ''),
            'TARIF BAYAR' => (string) ($row['tarif_bayar'] ?? ''),
            'Alasan Gagal' => $reason,
        ];
    }
}
```

- [ ] **Step 2: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Exports\ImportErrorsExport;
use App\Imports\AssignmentsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssignmentImportController extends Controller
{
    private const HEADINGS = [
        'NIK',
        'NO. DOKUMEN',
        'TANGGAL MULAI',
        'TANGGAL SELESAI',
        'TARIF JUAL',
        'TARIF BAYAR',
        'Alasan Gagal',
    ];

    public function create(): Response
    {
        return Inertia::render('Assignments/Import', [
            'result' => session('assignment_import_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new AssignmentsImport($request->user()->id);
        Excel::import($import, $request->file('file'));

        session(['assignment_import_errors' => $import->errors]);

        $errorCount = count($import->errors);

        $response = redirect()->route('assignments.import.create')
            ->with('assignment_import_result', [
                'created' => $import->created,
                'errorCount' => $errorCount,
            ]);

        if ($errorCount > 0) {
            return $response->with('error', "Import selesai dengan {$errorCount} baris gagal ({$import->created} berhasil). Lihat laporan error untuk detail.");
        }

        return $response->with('success', "Import selesai: {$import->created} berhasil, {$errorCount} gagal.");
    }

    public function downloadErrors(): BinaryFileResponse
    {
        $errors = session('assignment_import_errors', []);

        abort_if($errors === [], 404);

        session()->forget('assignment_import_errors');

        return Excel::download(
            new ImportErrorsExport(collect($errors), self::HEADINGS),
            'laporan-error-import-penugasan.xlsx',
        );
    }
}
```

- [ ] **Step 3: Register the routes**

In `routes/web.php`, add the import `use App\Http\Controllers\AssignmentImportController;` alphabetically among the existing `use App\Http\Controllers\...` lines. Then add these three routes inside the EXISTING `Route::middleware(['auth', 'role:admin|staff_input'])->prefix('assignments')->name('assignments.')->group(function () { ... })` block (the one currently containing `edit`/`update`/`end.form`/`end`), placed BEFORE the existing `/{assignment}/edit` route so the literal `/import` segment isn't captured by the `{assignment}` wildcard:

```php
Route::middleware(['auth', 'role:admin|staff_input'])->prefix('assignments')->name('assignments.')->group(function () {
    Route::get('/import', [AssignmentImportController::class, 'create'])->name('import.create');
    Route::post('/import', [AssignmentImportController::class, 'store'])->name('import.store');
    Route::get('/import/errors', [AssignmentImportController::class, 'downloadErrors'])->name('import.errors');

    Route::get('/{assignment}/edit', [AssignmentController::class, 'edit'])->name('edit')->whereNumber('assignment');
    Route::put('/{assignment}', [AssignmentController::class, 'update'])->name('update')->whereNumber('assignment');
    Route::get('/{assignment}/end', [AssignmentController::class, 'endForm'])->name('end.form')->whereNumber('assignment');
    Route::patch('/{assignment}/end', [AssignmentController::class, 'end'])->name('end')->whereNumber('assignment');
});
```

- [ ] **Step 4: Before writing tests, confirm the exact `JobPeriodStatus` enum cases**

Run: `cat app/Enums/JobPeriodStatus.php`

This plan's Step 5 tests assume the non-active case is named `Berakhir` (as seen in `database/factories/JobPeriodFactory.php`'s `berakhir()` state method) — confirm this matches the actual file before writing the test that uses it. If it differs, adjust the test accordingly.

- [ ] **Step 5: Write the feature test file**

Base this file on `tests/Feature/Jobs/JobImportTest.php`'s exact structure (`admin()`/`staffInput()`/`viewer()` helpers, `makeXlsx()` using `PhpOffice\PhpSpreadsheet\Spreadsheet`/`Xlsx`) — read that file first, then write this one following the same shape:

```php
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
use Illuminate\Support\Facades\Carbon;
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
            ['1234567890123456', 'PR-001', '2025-01-01', '', '1.234.567,89', '900.000'],
        ]);

        $this->actingAs($admin)->post('/assignments/import', ['file' => $file]);

        $assignment = Assignment::firstOrFail();
        $this->assertEqualsWithDelta(1234567.89, (float) $assignment->tarif_jual, 0.001);
        $this->assertEqualsWithDelta(900000.0, (float) $assignment->tarif_bayar, 0.001);
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
```

- [ ] **Step 6: Run the tests and confirm all pass**

Run: `php artisan test --filter=AssignmentImportTest`
Expected: PASS (16 tests)

- [ ] **Step 7: Format, run the full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add app/Imports/AssignmentsImport.php app/Http/Controllers/AssignmentImportController.php routes/web.php tests/Feature/Assignments/AssignmentImportTest.php
git commit -m "$(cat <<'EOF'
Add Assignment Excel import: import class, controller, and routes

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: `Assignments/Import.tsx` page + entry point on Jobs/Index.tsx

**Files:**
- Create: `resources/js/Pages/Assignments/Import.tsx`
- Modify: `resources/js/Pages/Jobs/Index.tsx`

**Interfaces:**
- Consumes: routes `assignments.import.create`, `assignments.import.store`, `assignments.import.errors` (Task 1). Inertia prop shape `{ result: { created: number; errorCount: number } | null }`.
- Produces: nothing further consumed by later tasks (this is the final task).

- [ ] **Step 1: Write the Import page**

```tsx
import { Button } from '@/Components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface ImportResult {
    created: number;
    errorCount: number;
}

export default function Import({
    result,
}: PageProps<{ result: ImportResult | null }>) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        file: File | null;
    }>({
        file: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('assignments.import.store'), {
            forceFormData: true,
            onSuccess: () => reset('file'),
        });
    };

    const close = () => router.visit(route('jobs.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Job & PR
                </h2>
            }
        >
            <Head title="Import Penugasan" />

            <Sheet
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <SheetContent
                    className="overflow-y-auto sm:max-w-lg"
                    aria-describedby={undefined}
                >
                    <SheetHeader>
                        <SheetTitle>Import Penugasan</SheetTitle>
                    </SheetHeader>

                    <div className="mt-6 space-y-6">
                        {result && (
                            <div className="rounded-lg border border-slate-200 p-4">
                                <h3 className="text-sm font-semibold text-pln-navy">
                                    Hasil Import Terakhir
                                </h3>
                                <dl className="mt-3 space-y-1 text-sm text-slate-600">
                                    <div className="flex justify-between">
                                        <dt>Berhasil</dt>
                                        <dd>{result.created}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt>Gagal</dt>
                                        <dd>{result.errorCount}</dd>
                                    </div>
                                </dl>

                                {result.errorCount > 0 && (
                                    <Button
                                        variant="outline"
                                        asChild
                                        className="mt-4"
                                    >
                                        <a
                                            href={route(
                                                'assignments.import.errors',
                                            )}
                                        >
                                            Download Laporan Error
                                        </a>
                                    </Button>
                                )}
                            </div>
                        )}

                        <div>
                            <p className="mb-4 text-sm text-slate-600">
                                Upload file Excel (.xlsx/.xls) dengan kolom
                                NIK, NO_DOKUMEN, TANGGAL_MULAI,
                                TANGGAL_SELESAI, TARIF_JUAL, TARIF_BAYAR.
                            </p>

                            <form onSubmit={submit}>
                                <input
                                    type="file"
                                    accept=".xlsx,.xls"
                                    onChange={(e) =>
                                        setData(
                                            'file',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                    className="block w-full text-sm text-slate-600"
                                />
                                {errors.file && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.file}
                                    </p>
                                )}

                                <div className="mt-6 flex justify-end">
                                    <Button
                                        disabled={processing || !data.file}
                                    >
                                        Upload
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 2: Add the "Import Penugasan" button to `Jobs/Index.tsx`**

In `resources/js/Pages/Jobs/Index.tsx`, inside the `canManage &&` block (the `<div className="ml-auto flex gap-2">` containing the existing Import/Export/Tambah Job buttons), add a new button right after the existing "Import" button:

```tsx
<Button variant="outline" asChild>
    <Link href={route('assignments.import.create')}>
        Import Penugasan
    </Link>
</Button>
```

So the full block becomes:

```tsx
{canManage && (
    <div className="ml-auto flex gap-2">
        <Button variant="outline" asChild>
            <Link href={route('jobs.import.create')}>
                Import
            </Link>
        </Button>
        <Button variant="outline" asChild>
            <Link href={route('assignments.import.create')}>
                Import Penugasan
            </Link>
        </Button>
        <Button variant="outline" asChild>
            <a href={route('jobs.export')}>
                Export
            </a>
        </Button>
        <Button asChild>
            <Link href={route('jobs.create')}>
                Tambah Job
            </Link>
        </Button>
    </div>
)}
```

- [ ] **Step 3: Verify the frontend compiles**

```bash
npx tsc --noEmit
```

Expected: no errors.

- [ ] **Step 4: Manual verification in the browser**

Confirm the dev server is running (`npm run dev` or `composer run dev`), log in as an `admin` user, navigate to `/jobs`, click "Import Penugasan", confirm the Sheet opens with the upload form, confirm closing it (X or clicking outside) navigates back to `/jobs`.

- [ ] **Step 5: Run the full backend test suite one more time, then commit**

```bash
php artisan test --compact
git add resources/js/Pages/Assignments/Import.tsx resources/js/Pages/Jobs/Index.tsx
git commit -m "$(cat <<'EOF'
Add Assignments/Import page and entry point on Jobs/Index

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```
