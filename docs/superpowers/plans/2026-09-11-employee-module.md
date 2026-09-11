# Data Karyawan (Employee) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a full Employee (Karyawan) module to WorkTrack — CRUD with NIK-uniqueness validation, role-based masking of sensitive fields, pagination/search/filter, and Excel import/export — on top of the existing Auth & Role foundation.

**Architecture:** A single `employees` table backs an `Employee` Eloquent model with a `status` enum. Reads are open to any authenticated role; writes (create/update/status toggle/import/export) are gated by the existing `role:admin|staff_input` middleware pattern. Masking of `nik`/`no_rekening` happens server-side in the controller response, never client-side. Import/export use `maatwebsite/excel` (already installed): import is a manual per-row loop (`ToCollection`, not `ToModel`+`WithValidation`) so custom duplicate-detection business logic can run per row; failed rows are held in the session just long enough for a one-time error-report download, never persisted to a database table.

**Tech Stack:** Laravel 13, PHP 8.4, `maatwebsite/excel` ^4.0 (already installed), `spatie/laravel-permission` (already installed), Inertia.js + React + TypeScript, PHPUnit (class-based).

**Spec:** [docs/superpowers/specs/2026-09-11-employee-design.md](../specs/2026-09-11-employee-design.md) (parent spec: [docs/superpowers/specs/2026-09-09-worktrack-mvp-design.md](../specs/2026-09-09-worktrack-mvp-design.md) §6.3, §8.1, K2)

## Global Constraints

- `nik` is a string column, unique, exactly 16 digits (`^\d{16}$`) — never numeric/integer (PRD Asumsi A4, spec §2).
- `no_rekening` is a string column, digits only, variable length — never numeric/integer (PRD Asumsi A4).
- No permanent delete anywhere in the UI — only a status toggle (`aktif` ↔ `non_aktif`) (PRD Asumsi A8, spec E4).
- Masking format: strings > 8 chars → first 4 + literal `******` (always 6 stars) + last 4; strings ≤ 8 chars → `******` entirely. Applied server-side only (spec §3.2).
- Reads (`index`, and the masked fields within it) are available to any authenticated user; writes are gated to `admin`/`staff_input` via the `role:admin|staff_input` middleware alias already registered in `bootstrap/app.php` (spec E2).
- Import: valid rows save immediately; NIK-duplicate-same-name rows are skipped (not an error); NIK-duplicate-different-name or malformed rows go into a downloadable Excel error report generated from in-memory data — no `import_errors` table, no queue (spec §3.4, E5).
- Export is full-dataset, unmasked, restricted to `admin`/`staff_input` only (spec §3.5).
- List pagination: 20 per page, with search (nama/nik) and status filter (spec E6).
- All commands run via Docker: `docker compose exec app <command>` for PHP/artisan/Pint, the `docker run ... node:20-alpine` one-shot for `npm run build`.
- After any PHP file change, run `vendor/bin/pint --dirty --format agent` before committing (project convention, `CLAUDE.md`).

---

### Task 1: Data foundation — migration, enum, model, factory, masking helper

**Files:**
- Create: `database/migrations/2026_09_11_000001_create_employees_table.php`
- Create: `app/Enums/EmployeeStatus.php`
- Create: `app/Models/Employee.php`
- Create: `database/factories/EmployeeFactory.php`
- Create: `app/Support/Masks.php`
- Test: `tests/Unit/MasksTest.php`

**Interfaces:**
- Produces: `Employee` model with attributes `id, nik, nama, alamat, no_rekening, nama_bank, status` (`status` cast to `EmployeeStatus` enum) — Tasks 2, 4, 6 create/query/update these. `Masks::partial(string $value): string` — Task 2 calls this for masked API responses. `EmployeeFactory` — Tasks 2, 4, 6's tests use `Employee::factory()`.

- [ ] **Step 1: Write the failing test for the masking helper**

```php
<?php

namespace Tests\Unit;

use App\Support\Masks;
use Tests\TestCase;

class MasksTest extends TestCase
{
    public function test_it_masks_a_string_longer_than_eight_characters(): void
    {
        $this->assertSame('3513******0001', Masks::partial('3513126804000001'));
    }

    public function test_it_fully_masks_a_string_of_eight_characters_or_fewer(): void
    {
        $this->assertSame('******', Masks::partial('1234567'));
        $this->assertSame('******', Masks::partial('12345678'));
    }

    public function test_it_uses_exactly_six_stars_regardless_of_original_length(): void
    {
        $this->assertSame('1234******9012', Masks::partial('123456789012'));
        $this->assertSame('1234******6789012', Masks::partial('1234567890123456789012'));
    }
}
```

Save this to `tests/Unit/MasksTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=MasksTest`
Expected: FAIL — `Class "App\Support\Masks" not found`.

- [ ] **Step 3: Write the `Masks` helper**

```php
<?php

namespace App\Support;

class Masks
{
    /**
     * Mask a sensitive value, keeping the first and last 4 characters
     * visible (or fully masking short values) using a fixed 6-star run
     * so the original length can't be inferred from the mask.
     */
    public static function partial(string $value): string
    {
        if (strlen($value) <= 8) {
            return '******';
        }

        return substr($value, 0, 4).'******'.substr($value, -4);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=MasksTest`
Expected: PASS (3/3).

- [ ] **Step 5: Create the `EmployeeStatus` enum**

```php
<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Aktif = 'aktif';
    case NonAktif = 'non_aktif';
}
```

Save this to `app/Enums/EmployeeStatus.php`.

- [ ] **Step 6: Create the migration**

Run: `docker compose exec app php artisan make:migration create_employees_table --no-interaction`

Replace the generated file's contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 16)->unique();
            $table->string('nama');
            $table->text('alamat');
            $table->string('no_rekening', 50);
            $table->string('nama_bank', 100)->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
```

- [ ] **Step 7: Create the `Employee` model**

```php
<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'nik', 'alamat', 'no_rekening', 'nama_bank', 'status'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
        ];
    }
}
```

- [ ] **Step 8: Create the factory**

```php
<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'nik' => fake()->unique()->numerify('################'),
            'alamat' => fake()->address(),
            'no_rekening' => fake()->unique()->numerify('##########'),
            'nama_bank' => fake()->randomElement(['BCA', 'BRI', 'Mandiri', 'BNI']),
            'status' => EmployeeStatus::Aktif,
        ];
    }

    public function nonAktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeStatus::NonAktif,
        ]);
    }
}
```

Note `fake()->numerify('################')` produces exactly 16 digit characters (16 `#` placeholders), matching the `^\d{16}$` NIK format.

- [ ] **Step 9: Run the migration and the full test suite**

Run: `docker compose exec app php artisan migrate`
Expected: `2026_09_11_000001_create_employees_table` marked `DONE`.

Run: `docker compose exec app php artisan test`
Expected: all existing tests still pass, plus the 3 new `MasksTest` tests (42 + 3 = 45).

- [ ] **Step 10: Format and commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add database/migrations/2026_09_11_000001_create_employees_table.php app/Enums/EmployeeStatus.php app/Models/Employee.php database/factories/EmployeeFactory.php app/Support/Masks.php tests/Unit/MasksTest.php
git commit -m "Add Employee data foundation (migration, model, enum, masking helper)"
```

---

### Task 2: Employee CRUD backend

**Files:**
- Create: `app/Http/Requests/StoreEmployeeRequest.php`
- Create: `app/Http/Requests/UpdateEmployeeRequest.php`
- Create: `app/Http/Controllers/EmployeeController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Employees/EmployeeCrudTest.php`

**Interfaces:**
- Consumes: `Employee` model, `EmployeeStatus` enum, `Masks::partial()` (Task 1); `role:admin|staff_input` middleware alias (already registered).
- Produces: routes `employees.index` (GET `/employees`), `employees.create` (GET `/employees/create`), `employees.store` (POST `/employees`), `employees.edit` (GET `/employees/{employee}/edit`), `employees.update` (PUT `/employees/{employee}`), `employees.toggle-status` (PATCH `/employees/{employee}/toggle-status`) — Task 3's frontend calls these by name. `Inertia::render('Employees/Index', ['employees' => <Laravel paginator array>, 'filters' => ['search' => ?string, 'status' => ?string], 'canManage' => bool])`. `Inertia::render('Employees/Create')` (no props). `Inertia::render('Employees/Edit', ['employee' => {id, nama, nik, alamat, no_rekening, nama_bank, status}])` — always the full (unmasked) values, since only `admin`/`staff_input` can reach this route.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
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

    public function test_viewer_sees_masked_nik_and_no_rekening_in_the_list(): void
    {
        $viewer = $this->viewer();
        Employee::factory()->create(['nik' => '3513126804000001', 'no_rekening' => '1923973699']);

        $response = $this->actingAs($viewer)->get('/employees');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->where('employees.data.0.nik', '3513******0001')
            ->where('employees.data.0.no_rekening', '******')
            ->where('canManage', false)
        );
    }

    public function test_admin_sees_full_nik_and_no_rekening_in_the_list(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '3513126804000001', 'no_rekening' => '1923973699']);

        $response = $this->actingAs($admin)->get('/employees');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('employees.data.0.nik', '3513126804000001')
            ->where('employees.data.0.no_rekening', '1923973699')
            ->where('canManage', true)
        );
    }

    public function test_viewer_cannot_create_an_employee(): void
    {
        $viewer = $this->viewer();

        $this->actingAs($viewer)->get('/employees/create')->assertForbidden();
        $this->actingAs($viewer)->post('/employees', [])->assertForbidden();
    }

    public function test_admin_can_create_an_employee_with_valid_data(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/employees', [
            'nama' => 'Budi Santoso',
            'nik' => '3513126804000099',
            'alamat' => 'Jl. Merdeka No. 1',
            'no_rekening' => '1923973699',
            'nama_bank' => 'BCA',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'nik' => '3513126804000099',
            'nama' => 'Budi Santoso',
            'status' => 'aktif',
        ]);
    }

    public function test_nik_must_be_exactly_sixteen_digits(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/employees', [
            'nama' => 'Budi Santoso',
            'nik' => '12345',
            'alamat' => 'Jl. Merdeka No. 1',
            'no_rekening' => '1923973699',
        ]);

        $response->assertSessionHasErrors('nik');
    }

    public function test_nik_must_be_unique(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nik' => '3513126804000099']);

        $response = $this->actingAs($admin)->post('/employees', [
            'nama' => 'Nama Lain',
            'nik' => '3513126804000099',
            'alamat' => 'Jl. Merdeka No. 2',
            'no_rekening' => '1111111111',
        ]);

        $response->assertSessionHasErrors('nik');
    }

    public function test_admin_can_update_an_employee_and_keep_its_own_nik(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['nik' => '3513126804000099']);

        $response = $this->actingAs($admin)->put("/employees/{$employee->id}", [
            'nama' => 'Nama Diperbarui',
            'nik' => '3513126804000099',
            'alamat' => 'Alamat Baru',
            'no_rekening' => '2222222222',
        ]);

        $response->assertRedirect(route('employees.index'));
        $employee->refresh();
        $this->assertSame('Nama Diperbarui', $employee->nama);
        $this->assertSame('2222222222', $employee->no_rekening);
    }

    public function test_admin_can_toggle_employee_status(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)
            ->patch("/employees/{$employee->id}/toggle-status")
            ->assertRedirect(route('employees.index'));

        $this->assertSame('non_aktif', $employee->fresh()->status->value);

        $this->actingAs($admin)->patch("/employees/{$employee->id}/toggle-status");

        $this->assertSame('aktif', $employee->fresh()->status->value);
    }

    public function test_search_filters_by_name_or_nik(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nama' => 'Budi Santoso', 'nik' => '1111111111111111']);
        Employee::factory()->create(['nama' => 'Siti Aminah', 'nik' => '2222222222222222']);

        $response = $this->actingAs($admin)->get('/employees?search=Budi');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.nama', 'Budi Santoso')
        );
    }

    public function test_status_filter_narrows_the_list(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nama' => 'Aktif Satu']);
        Employee::factory()->nonAktif()->create(['nama' => 'Non Aktif Satu']);

        $response = $this->actingAs($admin)->get('/employees?status=non_aktif');

        $response->assertInertia(fn (Assert $page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.nama', 'Non Aktif Satu')
        );
    }
}
```

Save this to `tests/Feature/Employees/EmployeeCrudTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=EmployeeCrudTest`
Expected: FAIL — no `/employees` routes exist yet (404s).

- [ ] **Step 3: Create the form requests**

`app/Http/Requests/StoreEmployeeRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Authorization is enforced by the `role:admin|staff_input` route
     * middleware — this request only validates the payload shape.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nik' => ['required', 'string', 'regex:/^\d{16}$/', 'unique:employees,nik'],
            'alamat' => ['required', 'string'],
            'no_rekening' => ['required', 'string', 'regex:/^\d+$/'],
            'nama_bank' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

`app/Http/Requests/UpdateEmployeeRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Authorization is enforced by the `role:admin|staff_input` route
     * middleware — this request only validates the payload shape.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nik' => [
                'required',
                'string',
                'regex:/^\d{16}$/',
                Rule::unique('employees', 'nik')->ignore($this->route('employee')),
            ],
            'alamat' => ['required', 'string'],
            'no_rekening' => ['required', 'string', 'regex:/^\d+$/'],
            'nama_bank' => ['nullable', 'string', 'max:100'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Support\Masks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $canManage = $request->user()->hasAnyRole(['admin', 'staff_input']);

        $employees = Employee::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        $employees->through(fn (Employee $employee) => [
            'id' => $employee->id,
            'nama' => $employee->nama,
            'nik' => $canManage ? $employee->nik : Masks::partial($employee->nik),
            'no_rekening' => $canManage ? $employee->no_rekening : Masks::partial($employee->no_rekening),
            'status' => $employee->status->value,
        ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'status']),
            'canManage' => $canManage,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Employees/Create');
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Employee::create([
            ...$request->validated(),
            'status' => EmployeeStatus::Aktif,
        ]);

        return redirect()->route('employees.index');
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('Employees/Edit', [
            'employee' => [
                'id' => $employee->id,
                'nama' => $employee->nama,
                'nik' => $employee->nik,
                'alamat' => $employee->alamat,
                'no_rekening' => $employee->no_rekening,
                'nama_bank' => $employee->nama_bank,
                'status' => $employee->status->value,
            ],
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return redirect()->route('employees.index');
    }

    public function toggleStatus(Employee $employee): RedirectResponse
    {
        $employee->update([
            'status' => $employee->status === EmployeeStatus::Aktif
                ? EmployeeStatus::NonAktif
                : EmployeeStatus::Aktif,
        ]);

        return redirect()->route('employees.index');
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, add the import near the top:

```php
use App\Http\Controllers\EmployeeController;
```

Then add this group before `require __DIR__.'/auth.php';` (after the existing `users` group):

```php
Route::middleware('auth')->prefix('employees')->name('employees.')->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])->name('index');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
        Route::patch('/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->name('toggle-status');
    });
});
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=EmployeeCrudTest`
Expected: PASS (10/10).

- [ ] **Step 7: Run the full test suite**

Run: `docker compose exec app php artisan test`
Expected: all tests pass (45 + 10 = 55).

- [ ] **Step 8: Format and commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Http/Requests/StoreEmployeeRequest.php app/Http/Requests/UpdateEmployeeRequest.php app/Http/Controllers/EmployeeController.php routes/web.php tests/Feature/Employees/EmployeeCrudTest.php
git commit -m "Add Employee CRUD backend with masking and pagination"
```

---

### Task 3: Employee CRUD frontend

**Files:**
- Create: `resources/js/Components/Pagination.tsx`
- Create: `resources/js/Pages/Employees/Index.tsx`
- Create: `resources/js/Pages/Employees/Create.tsx`
- Create: `resources/js/Pages/Employees/Edit.tsx`
- Modify: `resources/js/types/index.d.ts`
- Modify: `resources/js/Layouts/AuthenticatedLayout.tsx`

**Interfaces:**
- Consumes: `employees.index`/`employees.create`/`employees.store`/`employees.edit`/`employees.update`/`employees.toggle-status` routes and their exact prop shapes (Task 2).
- Produces: `PaginationLink` type and `Pagination` component — Task 5's Import page does not need it, but any future paginated list in this codebase can reuse it.

There is no automated frontend test in this plan; verification is a manual browser check per the Definition of Done.

- [ ] **Step 1: Add shared TypeScript types**

In `resources/js/types/index.d.ts`, add (keep the existing `RoleName`, `User`, `PageProps` as-is):

```ts
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}

export type EmployeeStatus = 'aktif' | 'non_aktif';

export interface EmployeeRow {
    id: number;
    nama: string;
    nik: string;
    no_rekening: string;
    status: EmployeeStatus;
}

export interface EmployeeDetail {
    id: number;
    nama: string;
    nik: string;
    alamat: string;
    no_rekening: string;
    nama_bank: string | null;
    status: EmployeeStatus;
}
```

- [ ] **Step 2: Create the `Pagination` component**

```tsx
import { Link } from '@inertiajs/react';
import { PaginationLink } from '@/types';

export default function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="mt-4 flex flex-wrap gap-1">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={`rounded px-3 py-1 text-sm ${
                            link.active
                                ? 'bg-pln-blue text-white'
                                : 'text-slate-600 hover:bg-slate-100'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={index}
                        className="cursor-not-allowed rounded px-3 py-1 text-sm text-slate-300"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </div>
    );
}
```

Save this to `resources/js/Components/Pagination.tsx`.

- [ ] **Step 3: Create the Employees index page**

```tsx
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EmployeeRow, PageProps, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Filters {
    search?: string;
    status?: string;
}

export default function Index({
    employees,
    filters,
    canManage,
}: PageProps<{
    employees: Paginated<EmployeeRow>;
    filters: Filters;
    canManage: boolean;
}>) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilters: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            route('employees.index'),
            { search, status },
            { preserveState: true, replace: true },
        );
    };

    const toggleStatus = (employeeId: number) => {
        router.patch(route('employees.toggle-status', employeeId));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Karyawan
                </h2>
            }
        >
            <Head title="Data Karyawan" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form
                            onSubmit={applyFilters}
                            className="mb-4 flex flex-wrap items-end gap-3"
                        >
                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Cari nama/NIK
                                </label>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) =>
                                        setSearch(e.target.value)
                                    }
                                    className="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Status
                                </label>
                                <select
                                    value={status}
                                    onChange={(e) =>
                                        setStatus(e.target.value)
                                    }
                                    className="mt-1 rounded-md border-slate-300 text-sm shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                >
                                    <option value="">Semua</option>
                                    <option value="aktif">Aktif</option>
                                    <option value="non_aktif">
                                        Non-aktif
                                    </option>
                                </select>
                            </div>

                            <button
                                type="submit"
                                className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                            >
                                Terapkan
                            </button>

                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    <Link
                                        href={route('employees.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Karyawan
                                    </Link>
                                </div>
                            )}
                        </form>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Nama
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            NIK
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            No. Rekening
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Status
                                        </th>
                                        {canManage && (
                                            <th className="px-3 py-2" />
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {employees.data.map((employee) => (
                                        <tr key={employee.id}>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                                {employee.nama}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {employee.nik}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {employee.no_rekening}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {employee.status ===
                                                'aktif'
                                                    ? 'Aktif'
                                                    : 'Non-aktif'}
                                            </td>
                                            {canManage && (
                                                <td className="whitespace-nowrap px-3 py-2 text-right text-sm">
                                                    <Link
                                                        href={route(
                                                            'employees.edit',
                                                            employee.id,
                                                        )}
                                                        className="mr-3 text-pln-blue hover:text-pln-blue-dark"
                                                    >
                                                        Edit
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            toggleStatus(
                                                                employee.id,
                                                            )
                                                        }
                                                        className="text-slate-500 hover:text-slate-700"
                                                    >
                                                        {employee.status ===
                                                        'aktif'
                                                            ? 'Nonaktifkan'
                                                            : 'Aktifkan'}
                                                    </button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Pagination links={employees.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

Save this to `resources/js/Pages/Employees/Index.tsx`. Deliberately no Import/Export buttons yet — Ziggy's `route()` throws immediately at render time (not just on click) for a name it doesn't recognize, so referencing `employees.import.create`/`employees.export` here before Tasks 4 and 6 register those routes would crash this page for every `admin`/`staff_input` user the moment they load it. Task 5 adds the Import button and Task 6 adds the Export button, each in the same task that registers its route.

- [ ] **Step 4: Create the Employees create page**

```tsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        nama: '',
        nik: '',
        alamat: '',
        no_rekening: '',
        nama_bank: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('employees.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Tambah Karyawan
                </h2>
            }
        >
            <Head title="Tambah Karyawan" />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel htmlFor="nama" value="Nama" />
                                <TextInput
                                    id="nama"
                                    className="mt-1 block w-full"
                                    value={data.nama}
                                    onChange={(e) =>
                                        setData('nama', e.target.value)
                                    }
                                    required
                                    isFocused
                                />
                                <InputError
                                    message={errors.nama}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel htmlFor="nik" value="NIK" />
                                <TextInput
                                    id="nik"
                                    className="mt-1 block w-full"
                                    value={data.nik}
                                    onChange={(e) =>
                                        setData('nik', e.target.value)
                                    }
                                    maxLength={16}
                                    required
                                />
                                <InputError
                                    message={errors.nik}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="alamat"
                                    value="Alamat"
                                />
                                <textarea
                                    id="alamat"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.alamat}
                                    onChange={(e) =>
                                        setData('alamat', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.alamat}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="no_rekening"
                                    value="No. Rekening"
                                />
                                <TextInput
                                    id="no_rekening"
                                    className="mt-1 block w-full"
                                    value={data.no_rekening}
                                    onChange={(e) =>
                                        setData(
                                            'no_rekening',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.no_rekening}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="nama_bank"
                                    value="Nama Bank (opsional)"
                                />
                                <TextInput
                                    id="nama_bank"
                                    className="mt-1 block w-full"
                                    value={data.nama_bank}
                                    onChange={(e) =>
                                        setData(
                                            'nama_bank',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.nama_bank}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton disabled={processing}>
                                    Simpan
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

Save this to `resources/js/Pages/Employees/Create.tsx`.

- [ ] **Step 5: Create the Employees edit page**

```tsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EmployeeDetail, PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Edit({
    employee,
}: PageProps<{ employee: EmployeeDetail }>) {
    const { data, setData, put, processing, errors } = useForm({
        nama: employee.nama,
        nik: employee.nik,
        alamat: employee.alamat,
        no_rekening: employee.no_rekening,
        nama_bank: employee.nama_bank ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('employees.update', employee.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Edit Karyawan: {employee.nama}
                </h2>
            }
        >
            <Head title={`Edit ${employee.nama}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel htmlFor="nama" value="Nama" />
                                <TextInput
                                    id="nama"
                                    className="mt-1 block w-full"
                                    value={data.nama}
                                    onChange={(e) =>
                                        setData('nama', e.target.value)
                                    }
                                    required
                                    isFocused
                                />
                                <InputError
                                    message={errors.nama}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel htmlFor="nik" value="NIK" />
                                <TextInput
                                    id="nik"
                                    className="mt-1 block w-full"
                                    value={data.nik}
                                    onChange={(e) =>
                                        setData('nik', e.target.value)
                                    }
                                    maxLength={16}
                                    required
                                />
                                <InputError
                                    message={errors.nik}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="alamat"
                                    value="Alamat"
                                />
                                <textarea
                                    id="alamat"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.alamat}
                                    onChange={(e) =>
                                        setData('alamat', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.alamat}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="no_rekening"
                                    value="No. Rekening"
                                />
                                <TextInput
                                    id="no_rekening"
                                    className="mt-1 block w-full"
                                    value={data.no_rekening}
                                    onChange={(e) =>
                                        setData(
                                            'no_rekening',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.no_rekening}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="nama_bank"
                                    value="Nama Bank (opsional)"
                                />
                                <TextInput
                                    id="nama_bank"
                                    className="mt-1 block w-full"
                                    value={data.nama_bank}
                                    onChange={(e) =>
                                        setData(
                                            'nama_bank',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.nama_bank}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton disabled={processing}>
                                    Simpan
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

Save this to `resources/js/Pages/Employees/Edit.tsx`.

- [ ] **Step 6: Add a "Karyawan" nav link visible to every authenticated role**

In `resources/js/Layouts/AuthenticatedLayout.tsx`, the desktop nav currently reads:

```tsx
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink>
                                {user.roles.includes('admin') && (
                                    <NavLink
                                        href={route('users.index')}
                                        active={route().current('users.*')}
                                    >
                                        Manajemen User
                                    </NavLink>
                                )}
```

Change it to (new "Karyawan" link right after Dashboard, visible to everyone, unlike the admin-only Manajemen User link):

```tsx
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink>
                                <NavLink
                                    href={route('employees.index')}
                                    active={route().current('employees.*')}
                                >
                                    Karyawan
                                </NavLink>
                                {user.roles.includes('admin') && (
                                    <NavLink
                                        href={route('users.index')}
                                        active={route().current('users.*')}
                                    >
                                        Manajemen User
                                    </NavLink>
                                )}
```

Do the equivalent for the mobile nav block (currently mirrors the desktop one with `ResponsiveNavLink`):

```tsx
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('employees.index')}
                            active={route().current('employees.*')}
                        >
                            Karyawan
                        </ResponsiveNavLink>
                        {user.roles.includes('admin') && (
                            <ResponsiveNavLink
                                href={route('users.index')}
                                active={route().current('users.*')}
                            >
                                Manajemen User
                            </ResponsiveNavLink>
                        )}
```

- [ ] **Step 7: Rebuild frontend assets**

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20-alpine sh -c "npm run build"
```

Expected: build completes with 0 TypeScript errors.

- [ ] **Step 8: Run the full backend test suite**

Run: `docker compose exec app php artisan test`
Expected: all 55 tests still pass (this task made no backend changes).

- [ ] **Step 9: Manual browser verification**

1. Log in as a `viewer`-role account (or use `worktrack:create-admin` / the Users UI from the Auth & Role module to make one if none exists).
2. Confirm a "Karyawan" nav link appears (unlike "Manajemen User", which stays hidden for non-admins) and clicking it shows the (empty, or seeded) employee list with no action buttons at all.
3. Log in as an `admin`. Confirm the page loads without error and a "Tambah Karyawan" button renders (there's no Import/Export button yet — those land in Tasks 5 and 6).
4. Click "Tambah Karyawan", fill the form, submit — confirm redirect back to the list and the new employee appears with full (unmasked) NIK/No. Rekening.
5. Click "Edit" on that employee, change the name, submit — confirm the list reflects the change.
6. Click "Nonaktifkan" — confirm the status column updates to "Non-aktif" without a page reload feel (Inertia partial update).

- [ ] **Step 10: Commit**

```bash
git add resources/js/Components/Pagination.tsx resources/js/Pages/Employees/Index.tsx resources/js/Pages/Employees/Create.tsx resources/js/Pages/Employees/Edit.tsx resources/js/types/index.d.ts resources/js/Layouts/AuthenticatedLayout.tsx
git commit -m "Add Employee CRUD frontend pages and nav link"
```

---

### Task 4: Import backend

**Files:**
- Create: `app/Imports/EmployeesImport.php`
- Create: `app/Exports/ImportErrorsExport.php`
- Create: `app/Http/Controllers/EmployeeImportController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Employees/EmployeeImportTest.php`

**Interfaces:**
- Consumes: `Employee` model (Task 1); `role:admin|staff_input` middleware group (already registered by Task 2 for the `employees` prefix).
- Produces: routes `employees.import.create` (GET `/employees/import`), `employees.import.store` (POST `/employees/import`), `employees.import.errors` (GET `/employees/import/errors`) — Task 5's frontend calls these by name and adds the Index page's "Import" link now that the route exists. `EmployeesImport` public properties `created: int`, `skipped: int`, `errors: array<int, array<string, string>>` (each error row keyed `NAMA`, `NIK`, `ALAMAT`, `NO REKENING`, `Alasan Gagal`) — read by the controller after `Excel::import()` runs.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
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

    private function makeXlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['NAMA', 'NIK', 'ALAMAT', 'NO REKENING'], null, 'A1');

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'karyawan.xlsx', null, null, true);
    }

    public function test_viewer_cannot_access_import(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get('/employees/import')->assertForbidden();
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
}
```

Save this to `tests/Feature/Employees/EmployeeImportTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=EmployeeImportTest`
Expected: FAIL — `/employees/import` routes don't exist yet.

- [ ] **Step 3: Create the import class**

```php
<?php

namespace App\Imports;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeesImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, string>> */
    public array $errors = [];

    public int $created = 0;

    public int $skipped = 0;

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $nama = trim((string) ($row['nama'] ?? ''));
            $nik = trim((string) ($row['nik'] ?? ''));
            $alamat = trim((string) ($row['alamat'] ?? ''));
            $noRekening = trim((string) ($row['no_rekening'] ?? ''));

            $reason = $this->validateRow($nama, $nik, $alamat, $noRekening);

            if ($reason !== null) {
                $this->recordError($nama, $nik, $alamat, $noRekening, $reason);

                continue;
            }

            $existing = Employee::where('nik', $nik)->first();

            if ($existing) {
                if ($existing->nama === $nama) {
                    $this->skipped++;

                    continue;
                }

                $this->recordError(
                    $nama,
                    $nik,
                    $alamat,
                    $noRekening,
                    "NIK sudah terdaftar atas nama lain: {$existing->nama}",
                );

                continue;
            }

            Employee::create([
                'nama' => $nama,
                'nik' => $nik,
                'alamat' => $alamat,
                'no_rekening' => $noRekening,
                'status' => 'aktif',
            ]);

            $this->created++;
        }
    }

    private function validateRow(string $nama, string $nik, string $alamat, string $noRekening): ?string
    {
        if ($nama === '') {
            return 'Nama wajib diisi';
        }

        if (! preg_match('/^\d{16}$/', $nik)) {
            return 'NIK harus 16 digit angka';
        }

        if ($alamat === '') {
            return 'Alamat wajib diisi';
        }

        if ($noRekening === '' || ! preg_match('/^\d+$/', $noRekening)) {
            return 'No. Rekening wajib diisi dan hanya boleh angka';
        }

        return null;
    }

    private function recordError(string $nama, string $nik, string $alamat, string $noRekening, string $reason): void
    {
        $this->errors[] = [
            'NAMA' => $nama,
            'NIK' => $nik,
            'ALAMAT' => $alamat,
            'NO REKENING' => $noRekening,
            'Alasan Gagal' => $reason,
        ];
    }
}
```

- [ ] **Step 4: Create the error report export class**

```php
<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportErrorsExport implements FromCollection, WithHeadings
{
    /**
     * @param  Collection<int, array<string, string>>  $errors
     */
    public function __construct(private readonly Collection $errors)
    {
    }

    public function collection(): Collection
    {
        return $this->errors;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['NAMA', 'NIK', 'ALAMAT', 'NO REKENING', 'Alasan Gagal'];
    }
}
```

- [ ] **Step 5: Create the import controller**

```php
<?php

namespace App\Http\Controllers;

use App\Exports\ImportErrorsExport;
use App\Imports\EmployeesImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Employees/Import', [
            'result' => session('employee_import_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $import = new EmployeesImport;
        Excel::import($import, $request->file('file'));

        if ($import->errors !== []) {
            session(['employee_import_errors' => $import->errors]);
        }

        return redirect()->route('employees.import.create')->with('employee_import_result', [
            'created' => $import->created,
            'skipped' => $import->skipped,
            'errorCount' => count($import->errors),
        ]);
    }

    public function downloadErrors(): BinaryFileResponse
    {
        $errors = session('employee_import_errors', []);

        abort_if($errors === [], 404);

        session()->forget('employee_import_errors');

        return Excel::download(
            new ImportErrorsExport(collect($errors)),
            'laporan-error-import-karyawan.xlsx',
        );
    }
}
```

- [ ] **Step 6: Register the routes**

In `routes/web.php`, add the import near the top:

```php
use App\Http\Controllers\EmployeeImportController;
```

Then add these routes inside the existing `role:admin|staff_input` sub-group under the `employees` prefix (alongside `create`/`store`/`edit`/`update`/`toggle-status`):

```php
        Route::get('/import', [EmployeeImportController::class, 'create'])->name('import.create');
        Route::post('/import', [EmployeeImportController::class, 'store'])->name('import.store');
        Route::get('/import/errors', [EmployeeImportController::class, 'downloadErrors'])->name('import.errors');
```

- [ ] **Step 7: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=EmployeeImportTest`
Expected: PASS (7/7).

- [ ] **Step 8: Run the full test suite**

Run: `docker compose exec app php artisan test`
Expected: all tests pass (55 + 7 = 62).

- [ ] **Step 9: Format and commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Imports/EmployeesImport.php app/Exports/ImportErrorsExport.php app/Http/Controllers/EmployeeImportController.php routes/web.php tests/Feature/Employees/EmployeeImportTest.php
git commit -m "Add Employee Excel import backend with error-report download"
```

---

### Task 5: Import frontend

**Files:**
- Create: `resources/js/Pages/Employees/Import.tsx`
- Modify: `resources/js/Pages/Employees/Index.tsx`

**Interfaces:**
- Consumes: `employees.import.create`/`employees.import.store`/`employees.import.errors` routes (Task 4). `result` prop shape `{ created: number; skipped: number; errorCount: number } | null`.
- Produces: nothing later tasks in this plan depend on.

There is no automated frontend test for this page; verification is manual per the Definition of Done.

- [ ] **Step 1: Create the Import page**

```tsx
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface ImportResult {
    created: number;
    skipped: number;
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
        post(route('employees.import.store'), {
            forceFormData: true,
            onSuccess: () => reset('file'),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Import Data Karyawan
                </h2>
            }
        >
            <Head title="Import Data Karyawan" />

            <div className="py-12">
                <div className="mx-auto max-w-xl space-y-6 sm:px-6 lg:px-8">
                    {result && (
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <h3 className="text-sm font-semibold text-pln-navy">
                                Hasil Import Terakhir
                            </h3>
                            <dl className="mt-3 space-y-1 text-sm text-slate-600">
                                <div className="flex justify-between">
                                    <dt>Berhasil</dt>
                                    <dd>{result.created}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Dilewati (sudah ada)</dt>
                                    <dd>{result.skipped}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Gagal</dt>
                                    <dd>{result.errorCount}</dd>
                                </div>
                            </dl>

                            {result.errorCount > 0 && (
                                <a
                                    href={route('employees.import.errors')}
                                    className="mt-4 inline-block rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                >
                                    Download Laporan Error
                                </a>
                            )}
                        </div>
                    )}

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <p className="mb-4 text-sm text-slate-600">
                            Upload file Excel (.xlsx/.xls) dengan kolom
                            NAMA, NIK, ALAMAT, NO REKENING.
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
                                <p className="mt-2 text-sm text-red-600">
                                    {errors.file}
                                </p>
                            )}

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton
                                    disabled={processing || !data.file}
                                >
                                    Upload
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

Save this to `resources/js/Pages/Employees/Import.tsx`.

- [ ] **Step 2: Add the "Import" button to the Employees index page**

Now that `employees.import.create` exists (Task 4), it's safe to reference it. In `resources/js/Pages/Employees/Index.tsx`, change:

```tsx
                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    <Link
                                        href={route('employees.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Karyawan
                                    </Link>
                                </div>
                            )}
```

to:

```tsx
                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    <Link
                                        href={route('employees.import.create')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Import
                                    </Link>
                                    <Link
                                        href={route('employees.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Karyawan
                                    </Link>
                                </div>
                            )}
```

- [ ] **Step 3: Rebuild frontend assets**

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20-alpine sh -c "npm run build"
```

Expected: 0 TypeScript errors.

- [ ] **Step 4: Run the full backend test suite**

Run: `docker compose exec app php artisan test`
Expected: all 62 tests still pass.

- [ ] **Step 5: Manual browser verification**

1. Log in as an admin. From the Employees list, confirm an "Import" button now appears next to "Tambah Karyawan" — click it.
2. Prepare a small `.xlsx` file with headers `NAMA, NIK, ALAMAT, NO REKENING` and 2-3 rows (one valid, one with a bad NIK). Upload it.
3. Confirm the summary shows correct counts and a "Download Laporan Error" link appears.
4. Click the download link — confirm an `.xlsx` file downloads with the failed row(s) and a reason column.
5. Reload the Import page (or click the link again) — confirm the download link is gone (session cleared) since there's no new `result` with `errorCount > 0` until another import runs.
6. Go back to the Employees list — confirm the valid row from the import appears.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Employees/Import.tsx resources/js/Pages/Employees/Index.tsx
git commit -m "Add Employee import frontend page and index Import button"
```

---

### Task 6: Export backend and Dashboard wiring

**Files:**
- Create: `app/Exports/EmployeesExport.php`
- Create: `app/Http/Controllers/EmployeeExportController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.tsx`
- Modify: `resources/js/Pages/Employees/Index.tsx`
- Test: `tests/Feature/Employees/EmployeeExportTest.php`
- Test: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `Employee` model (Task 1).
- Produces: route `employees.export` (GET `/employees/export`). `DashboardController::index()` now also passes `employeeCount: int`.

- [ ] **Step 1: Write the failing export test**

```php
<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_export(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get('/employees/export')->assertForbidden();
    }

    public function test_admin_can_export_unmasked_data(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Employee::factory()->create(['nik' => '3513126804000001']);

        $this->actingAs($admin)
            ->get('/employees/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
```

Save this to `tests/Feature/Employees/EmployeeExportTest.php`.

- [ ] **Step 2: Extend the failing dashboard test**

Read the current `tests/Feature/DashboardTest.php` first (it exists from the UI-redesign work and currently only asserts `roleCounts`). Add a new test method to that same file (don't remove the existing `test_dashboard_shows_user_counts_per_role` test):

```php
    public function test_dashboard_shows_employee_count(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        \App\Models\Employee::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('employeeCount', 3)
        );
    }
```

- [ ] **Step 3: Run both tests to verify they fail**

Run: `docker compose exec app php artisan test --filter=EmployeeExportTest`
Expected: FAIL — `/employees/export` doesn't exist (404).

Run: `docker compose exec app php artisan test --filter=DashboardTest`
Expected: FAIL — `employeeCount` prop doesn't exist.

- [ ] **Step 4: Create the export class**

```php
<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeesExport implements FromCollection, WithHeadings
{
    /**
     * @return Collection<int, array<int, string|null>>
     */
    public function collection(): Collection
    {
        return Employee::query()
            ->orderBy('nama')
            ->get()
            ->map(fn (Employee $employee) => [
                $employee->nama,
                $employee->nik,
                $employee->alamat,
                $employee->no_rekening,
                $employee->nama_bank,
                $employee->status->value,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Nama', 'NIK', 'Alamat', 'No. Rekening', 'Nama Bank', 'Status'];
    }
}
```

- [ ] **Step 5: Create the export controller**

```php
<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeExportController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return Excel::download(new EmployeesExport, 'data-karyawan.xlsx');
    }
}
```

- [ ] **Step 6: Register the export route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\EmployeeExportController;
```

Add this route inside the existing `role:admin|staff_input` sub-group under the `employees` prefix:

```php
        Route::get('/export', [EmployeeExportController::class, 'download'])->name('export');
```

- [ ] **Step 7: Add the "Export" button to the Employees index page**

Now that `employees.export` exists, it's safe to reference it. In `resources/js/Pages/Employees/Index.tsx`, change:

```tsx
                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    <Link
                                        href={route('employees.import.create')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Import
                                    </Link>
                                    <Link
                                        href={route('employees.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Karyawan
                                    </Link>
                                </div>
                            )}
```

to:

```tsx
                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    <Link
                                        href={route('employees.import.create')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Import
                                    </Link>
                                    <a
                                        href={route('employees.export')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Export
                                    </a>
                                    <Link
                                        href={route('employees.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Karyawan
                                    </Link>
                                </div>
                            )}
```

(Use a plain `<a>`, not Inertia's `<Link>`, for Export — it must trigger a normal browser download, not an Inertia XHR visit.)

- [ ] **Step 8: Wire the Dashboard's employee count**

In `app/Http/Controllers/DashboardController.php`, add the import `use App\Models\Employee;` and add `'employeeCount' => Employee::count(),` to the array passed to `Inertia::render('Dashboard', [...])`, alongside the existing `'roleCounts' => [...]`.

- [ ] **Step 9: Update the Dashboard's "Karyawan" bento card**

In `resources/js/Pages/Dashboard.tsx`, add `employeeCount` to the destructured props and its `PageProps` type (alongside the existing `roleCounts: RoleCounts`), then replace the "Karyawan" `BentoCard` — currently:

```tsx
                        <BentoCard
                            title="Karyawan"
                            accent="white"
                            badge="Segera Hadir"
                        >
                            <div className="text-2xl font-bold text-slate-300">
                                —
                            </div>
                        </BentoCard>
```

with:

```tsx
                        <BentoCard title="Karyawan" accent="white">
                            <div className="text-2xl font-bold text-pln-navy">
                                {employeeCount}
                            </div>
                        </BentoCard>
```

(Drop the `badge="Segera Hadir"` prop entirely — this card now shows real data.)

- [ ] **Step 10: Run tests to verify they pass**

Run: `docker compose exec app php artisan test --filter=EmployeeExportTest`
Expected: PASS (2/2).

Run: `docker compose exec app php artisan test --filter=DashboardTest`
Expected: PASS (2/2 — both the pre-existing role-counts test and the new employee-count test).

- [ ] **Step 11: Rebuild frontend and run the full suite**

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20-alpine sh -c "npm run build"
docker compose exec app php artisan test
```

Expected: 0 TypeScript errors; all 62 + 2 = 64 tests pass.

- [ ] **Step 12: Manual browser verification**

1. As an admin, on the Employees list, confirm the "Export" button now appears — click it and confirm an `.xlsx` file downloads containing all employees, full (unmasked) NIK/No. Rekening.
2. Go to the Dashboard — confirm the "Karyawan" bento card now shows the real employee count (no longer "Segera Hadir").
3. Log in as a `viewer` — confirm the Employees list has no Export button, and navigating directly to `/employees/export` returns a 403.

- [ ] **Step 13: Format and commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Exports/EmployeesExport.php app/Http/Controllers/EmployeeExportController.php routes/web.php app/Http/Controllers/DashboardController.php resources/js/Pages/Dashboard.tsx resources/js/Pages/Employees/Index.tsx tests/Feature/Employees/EmployeeExportTest.php tests/Feature/DashboardTest.php
git commit -m "Add Employee export and wire real employee count into Dashboard"
```

---

## Definition of Done

- `docker compose exec app php artisan test` passes with 64 tests, 0 failures.
- `npm run build` completes with 0 TypeScript errors.
- Any authenticated user can view the (masked, for `viewer`) Employee list with search/filter/pagination.
- `admin`/`staff_input` can create, edit, and toggle the status of employees; `viewer` gets 403 on all of those routes.
- Excel import saves valid rows, skips exact duplicates, and produces a downloadable error report for malformed or ambiguous-duplicate rows — all without a new database table.
- Excel export produces a full, unmasked `.xlsx` for `admin`/`staff_input` only.
- The Dashboard's "Karyawan" bento card shows a real count instead of "Segera Hadir".
- Nothing in this plan touches Job/JobPeriod/Assignment/Attendance domain code — that's the next sub-project.
