# Assignment & Riwayat Versi Pekerja Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Assignment module — assigning Employees to active JobPeriods with append-only version history, an "Akhiri Penugasan" action, a jumlah-TK warning, riwayat views per JobPeriod and per Employee, and the revised Job "Perbarui PR" scenario (c).

**Architecture:** New `Assignment` Eloquent model/table following the exact `#[Fillable]` + `casts()` + role-middleware pattern used by Employee/Job/JobPeriod. A new `AssignmentController` handles create/store, edit/update (with versioning branching logic), and end/endForm. A new `JobPeriodController::show` becomes the hub page (warning badge + assignment table + action links), reached from `Jobs/Show.tsx` and from the revised Job renew-scenario-(c). `EmployeeController::show` is added for the per-employee riwayat view. Every write action is gated by the existing `role:admin|staff_input` middleware; reads stay open to all authenticated users with `tarif_jual`/`tarif_bayar` masked for Viewer via the existing `Masks::partial()` helper.

**Tech Stack:** Laravel 13 (PHP 8.4), Inertia.js + React 18 + TypeScript, PostgreSQL, `spatie/laravel-permission`. No new packages.

**Spec:** [docs/superpowers/specs/2026-09-12-assignment-design.md](../specs/2026-09-12-assignment-design.md)

## Global Constraints

- No new composer/npm packages.
- Models use `#[Fillable([...])]` (PHP attribute) + `protected function casts(): array`, never `protected $fillable`.
- All write routes (create/store/update/end) sit behind `role:admin|staff_input` middleware; read routes (`show`) are open to any authenticated user.
- Any route whose numeric segment could collide with a literal sibling segment (e.g. `/create`) MUST have the literal route registered first, and numeric wildcard routes get `->whereNumber('<param>')`.
- Multi-write operations (versioning on date change) MUST be wrapped in `DB::transaction()`.
- `tarif_jual`/`tarif_bayar` are masked for Viewer using `App\Support\Masks::partial()` — identical treatment to NIK/no_rekening (per spec decision A5).
- A page/button that links to a route name MUST NOT be added before that route is registered (Ziggy `route()` throws at render time for unregistered names) — each task below only wires links to routes it itself registers.
- Enum cases use TitleCase keys (`Aktif`, `Selesai`, `Diperbarui`), matching `JobPeriodStatus`/`JobStatus`/`EmployeeStatus`.
- FormRequest `authorize()` always returns `true` — authorization is enforced by route middleware, matching every existing FormRequest in this codebase.
- Test files duplicate the `admin()`/`staffInput()`/`viewer()`/`inertiaHeaders()` private helpers per file (no shared trait) — this is the established convention in `tests/Feature/Jobs/*` and `tests/Feature/Employees/*`; do not introduce a shared trait.

---

### Task 1: Migration, Enum, Model, Factory

**Files:**
- Create: `database/migrations/2026_09_12_000003_create_assignments_table.php`
- Create: `app/Enums/AssignmentStatus.php`
- Create: `app/Models/Assignment.php`
- Create: `database/factories/AssignmentFactory.php`
- Modify: `app/Models/Employee.php`
- Modify: `app/Models/JobPeriod.php`
- Test: `tests/Feature/Assignments/AssignmentModelTest.php`

**Interfaces:**
- Produces: `Assignment` model with fields `employee_id`, `job_period_id`, `tanggal_mulai` (date), `tanggal_selesai` (date, nullable), `status` (`AssignmentStatus`), `is_current` (bool), `previous_assignment_id` (nullable FK to `assignments`), `tarif_jual`/`tarif_bayar` (`decimal:2`, nullable), `created_by` (FK to `users`). Relations: `employee(): BelongsTo`, `jobPeriod(): BelongsTo`, `previousAssignment(): BelongsTo`. `Employee::assignments(): HasMany`, `JobPeriod::assignments(): HasMany`.
- Consumes: nothing from other tasks (foundation task).

- [ ] **Step 1: Write the migration**

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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('job_period_id')->constrained('job_periods')->cascadeOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->string('status', 20)->default('aktif');
            $table->boolean('is_current')->default(true);
            $table->foreignId('previous_assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->decimal('tarif_jual', 12, 2)->nullable();
            $table->decimal('tarif_bayar', 12, 2)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['job_period_id', 'is_current']);
            $table->index(['employee_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `assignments` table created with no errors.

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Aktif = 'aktif';
    case Selesai = 'selesai';
    case Diperbarui = 'diperbarui';
}
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'job_period_id',
    'tanggal_mulai',
    'tanggal_selesai',
    'status',
    'is_current',
    'previous_assignment_id',
    'tarif_jual',
    'tarif_bayar',
    'created_by',
])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'is_current' => 'boolean',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tarif_jual' => 'decimal:2',
            'tarif_bayar' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<JobPeriod, $this>
     */
    public function jobPeriod(): BelongsTo
    {
        return $this->belongsTo(JobPeriod::class);
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function previousAssignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'previous_assignment_id');
    }
}
```

- [ ] **Step 5: Add the inverse relations to `Employee` and `JobPeriod`**

In `app/Models/Employee.php`, add the import `use Illuminate\Database\Eloquent\Relations\HasMany;` and this method inside the class:

```php
    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
```

In `app/Models/JobPeriod.php`, add the same import and method (identical body: `return $this->hasMany(Assignment::class);`).

- [ ] **Step 6: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'job_period_id' => JobPeriod::factory(),
            'tanggal_mulai' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'tanggal_selesai' => null,
            'status' => AssignmentStatus::Aktif,
            'is_current' => true,
            'tarif_jual' => fake()->randomFloat(2, 100000, 5000000),
            'tarif_bayar' => fake()->randomFloat(2, 80000, 4000000),
            'created_by' => User::factory(),
        ];
    }

    public function diperbarui(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::Diperbarui,
            'is_current' => false,
        ]);
    }

    public function selesai(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssignmentStatus::Selesai,
        ]);
    }
}
```

- [ ] **Step 7: Write the failing test, then implement until it passes**

```php
<?php

namespace Tests\Feature\Assignments;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_factory_creates_a_valid_row_with_correct_casts(): void
    {
        $assignment = Assignment::factory()->create();

        $this->assertSame(AssignmentStatus::Aktif, $assignment->status);
        $this->assertTrue($assignment->is_current);
        $this->assertIsString($assignment->tarif_jual);
        $this->assertInstanceOf(Carbon::class, $assignment->tanggal_mulai);
    }

    public function test_employee_has_many_assignments(): void
    {
        $employee = Employee::factory()->create();
        Assignment::factory()->count(2)->create(['employee_id' => $employee->id]);

        $this->assertCount(2, $employee->assignments);
    }

    public function test_job_period_has_many_assignments(): void
    {
        $jobPeriod = JobPeriod::factory()->create();
        Assignment::factory()->count(3)->create(['job_period_id' => $jobPeriod->id]);

        $this->assertCount(3, $jobPeriod->assignments);
    }

    public function test_previous_assignment_relation_resolves(): void
    {
        $old = Assignment::factory()->diperbarui()->create();
        $new = Assignment::factory()->create(['previous_assignment_id' => $old->id]);

        $this->assertTrue($new->previousAssignment->is($old));
    }
}
```

Run: `php artisan test --filter=AssignmentModelTest`
Expected: PASS (4 tests).

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_09_12_000003_create_assignments_table.php app/Enums/AssignmentStatus.php app/Models/Assignment.php app/Models/Employee.php app/Models/JobPeriod.php database/factories/AssignmentFactory.php tests/Feature/Assignments/AssignmentModelTest.php
git commit -m "Add Assignment model, migration, enum, and factory"
```

---

### Task 2: JobPeriod Detail Page (hub for Assignment)

**Files:**
- Create: `app/Http/Controllers/JobPeriodController.php` — add `show()` method (file already exists with `create()`/`store()`)
- Create: `resources/js/Pages/JobPeriods/Show.tsx`
- Modify: `routes/web.php`
- Modify: `resources/js/types/index.d.ts`
- Modify: `resources/js/Pages/Jobs/Show.tsx`
- Test: `tests/Feature/JobPeriods/JobPeriodShowTest.php`

**Interfaces:**
- Consumes: `Assignment` model, `Employee::assignments()`/`JobPeriod::assignments()` from Task 1; `App\Support\Masks::partial()` (existing).
- Produces: route `job-periods.show` (GET `/job-periods/{jobPeriod}`); Inertia component `JobPeriods/Show` with props `jobPeriod: JobPeriodDetail`, `assignments: AssignmentRow[]`, `activeAssignmentCount: number`, `warningJumlahTk: boolean`, `canManage: boolean`. This page has NO "Assign Karyawan"/"Edit"/"Akhiri" links yet — those are added in Tasks 3-5 alongside the routes they point to.

- [ ] **Step 1: Add types**

In `resources/js/types/index.d.ts`, append:

```ts
export type AssignmentStatus = 'aktif' | 'selesai' | 'diperbarui';

export interface AssignmentRow {
    id: number;
    employee_nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string | null;
    status: AssignmentStatus;
    is_current: boolean;
    tarif_jual: string | null;
    tarif_bayar: string | null;
}

export interface JobPeriodDetail {
    id: number;
    job_id: number;
    job_nama_pekerjaan: string;
    jenis_dokumen: DocumentType;
    no_dokumen: string;
    tanggal_mulai: string;
    tanggal_selesai: string | null;
    jumlah_tk_rencana: number;
    status: JobPeriodStatus;
}
```

- [ ] **Step 2: Add `JobPeriodController::show()`**

In `app/Http/Controllers/JobPeriodController.php`, add these imports: `App\Enums\AssignmentStatus`, `App\Models\Assignment`, `App\Support\Masks`, `Illuminate\Http\Request`. Add this method to the class:

```php
    public function show(Request $request, JobPeriod $jobPeriod): Response
    {
        $canManage = $request->user()->hasAnyRole(['admin', 'staff_input']);

        $assignments = $jobPeriod->assignments()->with('employee')->orderByDesc('tanggal_mulai')->get();
        $activeCount = $assignments->where('is_current', true)->where('status', AssignmentStatus::Aktif)->count();

        return Inertia::render('JobPeriods/Show', [
            'jobPeriod' => [
                'id' => $jobPeriod->id,
                'job_id' => $jobPeriod->job_id,
                'job_nama_pekerjaan' => $jobPeriod->job->nama_pekerjaan,
                'jenis_dokumen' => $jobPeriod->jenis_dokumen->value,
                'no_dokumen' => $jobPeriod->no_dokumen,
                'tanggal_mulai' => $jobPeriod->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $jobPeriod->tanggal_selesai?->format('Y-m-d'),
                'jumlah_tk_rencana' => $jobPeriod->jumlah_tk_rencana,
                'status' => $jobPeriod->status->value,
            ],
            'assignments' => $assignments->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'employee_nama' => $assignment->employee->nama,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $assignment->tanggal_selesai?->format('Y-m-d'),
                'status' => $assignment->status->value,
                'is_current' => $assignment->is_current,
                'tarif_jual' => $assignment->tarif_jual === null
                    ? null
                    : ($canManage ? (string) $assignment->tarif_jual : Masks::partial((string) $assignment->tarif_jual)),
                'tarif_bayar' => $assignment->tarif_bayar === null
                    ? null
                    : ($canManage ? (string) $assignment->tarif_bayar : Masks::partial((string) $assignment->tarif_bayar)),
            ])->values(),
            'activeAssignmentCount' => $activeCount,
            'warningJumlahTk' => $activeCount !== $jobPeriod->jumlah_tk_rencana,
            'canManage' => $canManage,
        ]);
    }
```

- [ ] **Step 3: Register the route**

In `routes/web.php`, add the import `use App\Http\Controllers\JobPeriodController;` (already imported). Add this new group after the `jobs` prefix group, before `require __DIR__.'/auth.php';`:

```php
Route::middleware('auth')->prefix('job-periods')->name('job-periods.')->group(function () {
    Route::get('/{jobPeriod}', [JobPeriodController::class, 'show'])->name('show')->whereNumber('jobPeriod');
});
```

- [ ] **Step 4: Write the page component**

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AssignmentRow, JobPeriodDetail, PageProps } from '@/types';
import { Head } from '@inertiajs/react';

export default function Show({
    jobPeriod,
    assignments,
    activeAssignmentCount,
    warningJumlahTk,
    canManage,
}: PageProps<{
    jobPeriod: JobPeriodDetail;
    assignments: AssignmentRow[];
    activeAssignmentCount: number;
    warningJumlahTk: boolean;
    canManage: boolean;
}>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    {jobPeriod.job_nama_pekerjaan} — {jobPeriod.jenis_dokumen}{' '}
                    {jobPeriod.no_dokumen}
                </h2>
            }
        >
            <Head title={`Periode ${jobPeriod.no_dokumen}`} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt className="text-slate-500">Periode</dt>
                                <dd className="font-medium">
                                    {jobPeriod.tanggal_mulai}
                                    {jobPeriod.tanggal_selesai
                                        ? ` s/d ${jobPeriod.tanggal_selesai}`
                                        : ''}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-slate-500">Status</dt>
                                <dd className="font-medium">
                                    {jobPeriod.status}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-slate-500">
                                    Jumlah TK Rencana
                                </dt>
                                <dd className="font-medium">
                                    {jobPeriod.jumlah_tk_rencana}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-slate-500">
                                    Assignment Aktif
                                </dt>
                                <dd className="font-medium">
                                    {activeAssignmentCount}
                                </dd>
                            </div>
                        </dl>

                        {warningJumlahTk && (
                            <div className="mt-4 rounded-lg bg-pln-yellow/20 p-3 text-sm text-pln-navy">
                                Jumlah TK aktif ({activeAssignmentCount}) tidak
                                sama dengan rencana (
                                {jobPeriod.jumlah_tk_rencana}).
                            </div>
                        )}
                    </div>

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="mb-4 text-sm font-semibold text-pln-navy">
                            Daftar Penugasan
                        </h3>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Karyawan
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Periode
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {assignments.map((assignment) => (
                                        <tr key={assignment.id}>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                                {assignment.employee_nama}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {assignment.tanggal_mulai}
                                                {assignment.tanggal_selesai
                                                    ? ` s/d ${assignment.tanggal_selesai}`
                                                    : ''}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {assignment.status}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 5: Link from `Jobs/Show.tsx`**

In `resources/js/Pages/Jobs/Show.tsx`, add a new `<th>Aksi</th>` after the "Status" header, and a matching `<td>` in the row mapping:

```tsx
<td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
    <Link
        href={route('job-periods.show', period.id)}
        className="text-pln-blue hover:underline"
    >
        Detail
    </Link>
</td>
```

(`Link` is already imported in this file.)

- [ ] **Step 6: Write the failing test, then implement until it passes**

```php
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
```

Run: `php artisan test --filter=JobPeriodShowTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Format, run full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add app/Http/Controllers/JobPeriodController.php routes/web.php resources/js/types/index.d.ts resources/js/Pages/JobPeriods/Show.tsx resources/js/Pages/Jobs/Show.tsx tests/Feature/JobPeriods/JobPeriodShowTest.php
git commit -m "Add JobPeriod detail page with assignment list and jumlah-TK warning"
```

---

### Task 3: Assign Karyawan (Create Assignment)

**Files:**
- Create: `app/Http/Requests/StoreAssignmentRequest.php`
- Modify: `app/Http/Controllers/AssignmentController.php` (new file — create it)
- Create: `resources/js/Pages/Assignments/Create.tsx`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/JobPeriods/Show.tsx` (add "Assign Karyawan" button)
- Test: `tests/Feature/Assignments/AssignmentCreateTest.php`

**Interfaces:**
- Consumes: `Assignment`, `AssignmentStatus` (Task 1); `job-periods.show` route (Task 2, used as the redirect target and back-link).
- Produces: routes `job-periods.assignments.create` (GET `/job-periods/{jobPeriod}/assignments/create`), `job-periods.assignments.store` (POST `/job-periods/{jobPeriod}/assignments`). New file `app/Http/Controllers/AssignmentController.php` — later tasks (4, 5) add more methods to this same file.

- [ ] **Step 1: Write the form request**

```php
<?php

namespace App\Http\Requests;

use App\Enums\EmployeeStatus;
use App\Models\Assignment;
use App\Models\Employee;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'tarif_jual' => ['nullable', 'numeric', 'min:0'],
            'tarif_bayar' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $employeeId = $this->input('employee_id');

            if (! $employeeId) {
                return;
            }

            $employee = Employee::find($employeeId);

            if ($employee && $employee->status !== EmployeeStatus::Aktif) {
                $validator->errors()->add('employee_id', 'Karyawan harus berstatus aktif.');
            }

            $jobPeriod = $this->route('jobPeriod');

            if ($jobPeriod && Assignment::where('employee_id', $employeeId)
                ->where('job_period_id', $jobPeriod->id)
                ->where('is_current', true)
                ->exists()) {
                $validator->errors()->add('employee_id', 'Karyawan ini sudah punya penugasan aktif pada periode ini.');
            }
        });
    }
}
```

- [ ] **Step 2: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\EmployeeStatus;
use App\Enums\JobPeriodStatus;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function create(JobPeriod $jobPeriod): Response
    {
        abort_if($jobPeriod->status !== JobPeriodStatus::Aktif, 403);

        return Inertia::render('Assignments/Create', [
            'jobPeriod' => [
                'id' => $jobPeriod->id,
                'no_dokumen' => $jobPeriod->no_dokumen,
            ],
            'employees' => Employee::where('status', EmployeeStatus::Aktif)
                ->orderBy('nama')
                ->get(['id', 'nama'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'nama' => $employee->nama,
                ]),
        ]);
    }

    public function store(StoreAssignmentRequest $request, JobPeriod $jobPeriod): RedirectResponse
    {
        abort_if($jobPeriod->status !== JobPeriodStatus::Aktif, 403);

        Assignment::create([
            'employee_id' => $request->validated('employee_id'),
            'job_period_id' => $jobPeriod->id,
            'tanggal_mulai' => $request->validated('tanggal_mulai'),
            'tanggal_selesai' => $request->validated('tanggal_selesai'),
            'tarif_jual' => $request->validated('tarif_jual'),
            'tarif_bayar' => $request->validated('tarif_bayar'),
            'status' => AssignmentStatus::Aktif,
            'is_current' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('job-periods.show', $jobPeriod);
    }
}
```

- [ ] **Step 3: Register the routes**

In `routes/web.php`, add the import `use App\Http\Controllers\AssignmentController;`. Update the `job-periods` group added in Task 2 to:

```php
Route::middleware('auth')->prefix('job-periods')->name('job-periods.')->group(function () {
    Route::get('/{jobPeriod}', [JobPeriodController::class, 'show'])->name('show')->whereNumber('jobPeriod');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/{jobPeriod}/assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
        Route::post('/{jobPeriod}/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    });
});
```

- [ ] **Step 4: Write the page component**

```tsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EmployeeOption {
    id: number;
    nama: string;
}

interface AssignJobPeriod {
    id: number;
    no_dokumen: string;
}

export default function Create({
    jobPeriod,
    employees,
}: PageProps<{ jobPeriod: AssignJobPeriod; employees: EmployeeOption[] }>) {
    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        tanggal_mulai: '',
        tanggal_selesai: '',
        tarif_jual: '',
        tarif_bayar: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('job-periods.assignments.store', jobPeriod.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Assign Karyawan: {jobPeriod.no_dokumen}
                </h2>
            }
        >
            <Head title={`Assign Karyawan - ${jobPeriod.no_dokumen}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel
                                    htmlFor="employee_id"
                                    value="Karyawan"
                                />
                                <select
                                    id="employee_id"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.employee_id}
                                    onChange={(e) =>
                                        setData('employee_id', e.target.value)
                                    }
                                    required
                                >
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={employee.id}
                                        >
                                            {employee.nama}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.employee_id}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tanggal_mulai"
                                    value="Tanggal Mulai"
                                />
                                <TextInput
                                    id="tanggal_mulai"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.tanggal_mulai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_mulai',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.tanggal_mulai}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tanggal_selesai"
                                    value="Tanggal Selesai (opsional)"
                                />
                                <TextInput
                                    id="tanggal_selesai"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.tanggal_selesai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_selesai',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.tanggal_selesai}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tarif_jual"
                                    value="Tarif Jual (opsional)"
                                />
                                <TextInput
                                    id="tarif_jual"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.tarif_jual}
                                    onChange={(e) =>
                                        setData('tarif_jual', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.tarif_jual}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tarif_bayar"
                                    value="Tarif Bayar (opsional)"
                                />
                                <TextInput
                                    id="tarif_bayar"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.tarif_bayar}
                                    onChange={(e) =>
                                        setData('tarif_bayar', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.tarif_bayar}
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

- [ ] **Step 5: Add the "Assign Karyawan" button to `JobPeriods/Show.tsx`**

Add `Link` to the existing `@inertiajs/react` import (it currently only imports `Head`). Insert this block right after the closing `</dl>` and before the `warningJumlahTk` conditional, gated by `canManage` (add `canManage` handling exactly as in `Jobs/Show.tsx`):

```tsx
{canManage && (
    <div className="mt-4">
        <Link
            href={route(
                'job-periods.assignments.create',
                jobPeriod.id,
            )}
            className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
        >
            Assign Karyawan
        </Link>
    </div>
)}
```

- [ ] **Step 6: Write the failing test, then implement until it passes**

```php
<?php

namespace Tests\Feature\Assignments;

use App\Enums\AssignmentStatus;
use App\Enums\JobPeriodStatus;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentCreateTest extends TestCase
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

    public function test_viewer_cannot_access_assign_form_or_submit(): void
    {
        $viewer = $this->viewer();
        $period = JobPeriod::factory()->create();

        $this->actingAs($viewer)->get("/job-periods/{$period->id}/assignments/create")->assertForbidden();
        $this->actingAs($viewer)->post("/job-periods/{$period->id}/assignments", [])->assertForbidden();
    }

    public function test_admin_can_assign_an_active_employee_to_an_active_period(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($admin)->post("/job-periods/{$period->id}/assignments", [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2025-09-01',
            'tarif_jual' => 500000,
            'tarif_bayar' => 400000,
        ]);

        $response->assertRedirect(route('job-periods.show', $period));

        $assignment = Assignment::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(AssignmentStatus::Aktif, $assignment->status);
        $this->assertTrue($assignment->is_current);
        $this->assertSame($period->id, $assignment->job_period_id);
    }

    public function test_cannot_assign_to_an_inactive_job_period(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->berakhir()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)->post("/job-periods/{$period->id}/assignments", [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2025-09-01',
        ])->assertForbidden();
    }

    public function test_cannot_assign_an_inactive_employee(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->create();
        $employee = Employee::factory()->nonAktif()->create();

        $response = $this->actingAs($admin)->post("/job-periods/{$period->id}/assignments", [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2025-09-01',
        ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_cannot_assign_the_same_employee_twice_while_current(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->create();
        $employee = Employee::factory()->create();
        Assignment::factory()->create([
            'employee_id' => $employee->id,
            'job_period_id' => $period->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post("/job-periods/{$period->id}/assignments", [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2025-09-01',
        ]);

        $response->assertSessionHasErrors('employee_id');
    }
}
```

Run: `php artisan test --filter=AssignmentCreateTest`
Expected: PASS (5 tests).

- [ ] **Step 7: Format, run full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add app/Http/Requests/StoreAssignmentRequest.php app/Http/Controllers/AssignmentController.php resources/js/Pages/Assignments/Create.tsx routes/web.php resources/js/Pages/JobPeriods/Show.tsx tests/Feature/Assignments/AssignmentCreateTest.php
git commit -m "Add Assign Karyawan flow (create Assignment)"
```

---

### Task 4: Edit Assignment (versioning on date change)

**Files:**
- Create: `app/Http/Requests/UpdateAssignmentRequest.php`
- Modify: `app/Http/Controllers/AssignmentController.php` (add `edit()`/`update()`)
- Create: `resources/js/Pages/Assignments/Edit.tsx`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/JobPeriods/Show.tsx` (add "Edit" link per current assignment row)
- Test: `tests/Feature/Assignments/AssignmentUpdateTest.php`

**Interfaces:**
- Consumes: `Assignment`, `AssignmentStatus` (Task 1); `job-periods.show` (Task 2).
- Produces: routes `assignments.edit` (GET `/assignments/{assignment}/edit`), `assignments.update` (PUT `/assignments/{assignment}`).

- [ ] **Step 1: Write the form request**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssignmentRequest extends FormRequest
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
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'tarif_jual' => ['nullable', 'numeric', 'min:0'],
            'tarif_bayar' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

- [ ] **Step 2: Add `edit()`/`update()` to `AssignmentController`**

Add imports `App\Http\Requests\UpdateAssignmentRequest`, `App\Models\Assignment` (already imported), `Illuminate\Support\Facades\DB`. Add these methods:

```php
    public function edit(Assignment $assignment): Response
    {
        abort_if(! $assignment->is_current, 404);

        return Inertia::render('Assignments/Edit', [
            'assignment' => [
                'id' => $assignment->id,
                'employee_nama' => $assignment->employee->nama,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $assignment->tanggal_selesai?->format('Y-m-d'),
                'tarif_jual' => $assignment->tarif_jual,
                'tarif_bayar' => $assignment->tarif_bayar,
            ],
        ]);
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        abort_if(! $assignment->is_current, 404);

        $data = $request->validated();

        $dateChanged = $data['tanggal_mulai'] !== $assignment->tanggal_mulai->format('Y-m-d')
            || ($data['tanggal_selesai'] ?? null) !== $assignment->tanggal_selesai?->format('Y-m-d');

        if ($dateChanged) {
            DB::transaction(function () use ($data, $assignment, $request) {
                $assignment->update([
                    'status' => AssignmentStatus::Diperbarui,
                    'is_current' => false,
                ]);

                Assignment::create([
                    'employee_id' => $assignment->employee_id,
                    'job_period_id' => $assignment->job_period_id,
                    'tanggal_mulai' => $data['tanggal_mulai'],
                    'tanggal_selesai' => $data['tanggal_selesai'],
                    'tarif_jual' => $data['tarif_jual'],
                    'tarif_bayar' => $data['tarif_bayar'],
                    'status' => AssignmentStatus::Aktif,
                    'is_current' => true,
                    'previous_assignment_id' => $assignment->id,
                    'created_by' => $request->user()->id,
                ]);
            });
        } else {
            $assignment->update([
                'tarif_jual' => $data['tarif_jual'],
                'tarif_bayar' => $data['tarif_bayar'],
            ]);
        }

        return redirect()->route('job-periods.show', $assignment->job_period_id);
    }
```

- [ ] **Step 3: Register the routes**

In `routes/web.php`, add this new group after the `job-periods` group:

```php
Route::middleware(['auth', 'role:admin|staff_input'])->prefix('assignments')->name('assignments.')->group(function () {
    Route::get('/{assignment}/edit', [AssignmentController::class, 'edit'])->name('edit');
    Route::put('/{assignment}', [AssignmentController::class, 'update'])->name('update');
});
```

- [ ] **Step 4: Write the page component**

```tsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EditAssignmentInfo {
    id: number;
    employee_nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string | null;
    tarif_jual: string | null;
    tarif_bayar: string | null;
}

export default function Edit({
    assignment,
}: PageProps<{ assignment: EditAssignmentInfo }>) {
    const { data, setData, put, processing, errors } = useForm({
        tanggal_mulai: assignment.tanggal_mulai,
        tanggal_selesai: assignment.tanggal_selesai ?? '',
        tarif_jual: assignment.tarif_jual ?? '',
        tarif_bayar: assignment.tarif_bayar ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('assignments.update', assignment.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Edit Penugasan: {assignment.employee_nama}
                </h2>
            }
        >
            <Head title={`Edit Penugasan - ${assignment.employee_nama}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel
                                    htmlFor="tanggal_mulai"
                                    value="Tanggal Mulai"
                                />
                                <TextInput
                                    id="tanggal_mulai"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.tanggal_mulai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_mulai',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.tanggal_mulai}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tanggal_selesai"
                                    value="Tanggal Selesai (opsional)"
                                />
                                <TextInput
                                    id="tanggal_selesai"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.tanggal_selesai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_selesai',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.tanggal_selesai}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tarif_jual"
                                    value="Tarif Jual (opsional)"
                                />
                                <TextInput
                                    id="tarif_jual"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.tarif_jual}
                                    onChange={(e) =>
                                        setData('tarif_jual', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.tarif_jual}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tarif_bayar"
                                    value="Tarif Bayar (opsional)"
                                />
                                <TextInput
                                    id="tarif_bayar"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.tarif_bayar}
                                    onChange={(e) =>
                                        setData('tarif_bayar', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.tarif_bayar}
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

- [ ] **Step 5: Add "Edit" link to `JobPeriods/Show.tsx`**

Add a new `<th>Aksi</th>` (gated by `canManage`) and, in the row mapping, a matching `<td>` gated by `canManage && assignment.is_current`:

```tsx
{canManage && assignment.is_current && (
    <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
        <Link
            href={route('assignments.edit', assignment.id)}
            className="text-pln-blue hover:underline"
        >
            Edit
        </Link>
    </td>
)}
```

(If `canManage` is false, or the assignment is not current, render an empty `<td />` in that column so the table stays aligned — follow whatever conditional-column pattern `Jobs/Index.tsx` already uses for its `canManage`-gated column.)

- [ ] **Step 6: Write the failing test, then implement until it passes**

```php
<?php

namespace Tests\Feature\Assignments;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentUpdateTest extends TestCase
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

    public function test_viewer_cannot_access_edit_form_or_submit(): void
    {
        $viewer = $this->viewer();
        $assignment = Assignment::factory()->create();

        $this->actingAs($viewer)->get("/assignments/{$assignment->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->put("/assignments/{$assignment->id}", [])->assertForbidden();
    }

    public function test_changing_dates_creates_a_new_version_and_closes_the_old_one(): void
    {
        $admin = $this->admin();
        $old = Assignment::factory()->create([
            'created_by' => $admin->id,
            'tanggal_mulai' => '2025-09-01',
        ]);

        $response = $this->actingAs($admin)->put("/assignments/{$old->id}", [
            'tanggal_mulai' => '2025-09-10',
            'tanggal_selesai' => null,
            'tarif_jual' => $old->tarif_jual,
            'tarif_bayar' => $old->tarif_bayar,
        ]);

        $response->assertRedirect(route('job-periods.show', $old->job_period_id));

        $old->refresh();
        $this->assertSame(AssignmentStatus::Diperbarui, $old->status);
        $this->assertFalse($old->is_current);

        $new = Assignment::where('previous_assignment_id', $old->id)->firstOrFail();
        $this->assertTrue($new->is_current);
        $this->assertSame(AssignmentStatus::Aktif, $new->status);
        $this->assertSame('2025-09-10', $new->tanggal_mulai->format('Y-m-d'));
    }

    public function test_changing_only_tarif_updates_in_place_without_a_new_version(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->create([
            'created_by' => $admin->id,
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => null,
        ]);

        $this->actingAs($admin)->put("/assignments/{$assignment->id}", [
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => null,
            'tarif_jual' => 999999,
            'tarif_bayar' => 888888,
        ]);

        $assignment->refresh();
        $this->assertSame('999999.00', $assignment->tarif_jual);
        $this->assertTrue($assignment->is_current);
        $this->assertSame(AssignmentStatus::Aktif, $assignment->status);
        $this->assertSame(0, Assignment::where('previous_assignment_id', $assignment->id)->count());
    }

    public function test_cannot_edit_a_non_current_assignment(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->diperbarui()->create();

        $this->actingAs($admin)->get("/assignments/{$assignment->id}/edit")->assertNotFound();
    }
}
```

Run: `php artisan test --filter=AssignmentUpdateTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Format, run full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add app/Http/Requests/UpdateAssignmentRequest.php app/Http/Controllers/AssignmentController.php resources/js/Pages/Assignments/Edit.tsx routes/web.php resources/js/Pages/JobPeriods/Show.tsx tests/Feature/Assignments/AssignmentUpdateTest.php
git commit -m "Add Assignment edit with append-only versioning on date change"
```

---

### Task 5: Akhiri Penugasan (end without a successor)

**Files:**
- Create: `app/Http/Requests/EndAssignmentRequest.php`
- Modify: `app/Http/Controllers/AssignmentController.php` (add `endForm()`/`end()`)
- Create: `resources/js/Pages/Assignments/End.tsx`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/JobPeriods/Show.tsx` (add "Akhiri" link per current, active assignment row)
- Test: `tests/Feature/Assignments/AssignmentEndTest.php`

**Interfaces:**
- Consumes: `Assignment`, `AssignmentStatus` (Task 1); `job-periods.show` (Task 2).
- Produces: routes `assignments.end.form` (GET `/assignments/{assignment}/end`), `assignments.end` (PATCH `/assignments/{assignment}/end`).

- [ ] **Step 1: Write the form request**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EndAssignmentRequest extends FormRequest
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
            'tanggal_selesai' => [
                'required',
                'date',
                'after_or_equal:'.$this->route('assignment')->tanggal_mulai->format('Y-m-d'),
            ],
        ];
    }
}
```

- [ ] **Step 2: Add `endForm()`/`end()` to `AssignmentController`**

Add import `App\Http\Requests\EndAssignmentRequest`. Add these methods:

```php
    public function endForm(Assignment $assignment): Response
    {
        abort_if(! $assignment->is_current || $assignment->status !== AssignmentStatus::Aktif, 404);

        return Inertia::render('Assignments/End', [
            'assignment' => [
                'id' => $assignment->id,
                'employee_nama' => $assignment->employee->nama,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
            ],
        ]);
    }

    public function end(EndAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        abort_if(! $assignment->is_current || $assignment->status !== AssignmentStatus::Aktif, 404);

        $assignment->update([
            'tanggal_selesai' => $request->validated('tanggal_selesai'),
            'status' => AssignmentStatus::Selesai,
        ]);

        return redirect()->route('job-periods.show', $assignment->job_period_id);
    }
```

- [ ] **Step 3: Register the routes**

In `routes/web.php`, add to the `assignments` group created in Task 4:

```php
Route::middleware(['auth', 'role:admin|staff_input'])->prefix('assignments')->name('assignments.')->group(function () {
    Route::get('/{assignment}/edit', [AssignmentController::class, 'edit'])->name('edit');
    Route::put('/{assignment}', [AssignmentController::class, 'update'])->name('update');
    Route::get('/{assignment}/end', [AssignmentController::class, 'endForm'])->name('end.form');
    Route::patch('/{assignment}/end', [AssignmentController::class, 'end'])->name('end');
});
```

- [ ] **Step 4: Write the page component**

```tsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EndAssignmentInfo {
    id: number;
    employee_nama: string;
    tanggal_mulai: string;
}

export default function End({
    assignment,
}: PageProps<{ assignment: EndAssignmentInfo }>) {
    const { data, setData, patch, processing, errors } = useForm({
        tanggal_selesai: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('assignments.end', assignment.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Akhiri Penugasan: {assignment.employee_nama}
                </h2>
            }
        >
            <Head title={`Akhiri Penugasan - ${assignment.employee_nama}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel
                                    htmlFor="tanggal_selesai"
                                    value="Tanggal Akhir"
                                />
                                <TextInput
                                    id="tanggal_selesai"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.tanggal_selesai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_selesai',
                                            e.target.value,
                                        )
                                    }
                                    min={assignment.tanggal_mulai}
                                    required
                                    isFocused
                                />
                                <InputError
                                    message={errors.tanggal_selesai}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton disabled={processing}>
                                    Akhiri Penugasan
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

- [ ] **Step 5: Add "Akhiri" link to `JobPeriods/Show.tsx`**

In the `canManage && assignment.is_current` action cell added in Task 4, add a second link next to "Edit", gated additionally on `assignment.status === 'aktif'`:

```tsx
{assignment.status === 'aktif' && (
    <Link
        href={route('assignments.end.form', assignment.id)}
        className="ml-3 text-red-600 hover:underline"
    >
        Akhiri
    </Link>
)}
```

- [ ] **Step 6: Write the failing test, then implement until it passes**

```php
<?php

namespace Tests\Feature\Assignments;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentEndTest extends TestCase
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

    public function test_viewer_cannot_access_end_form_or_submit(): void
    {
        $viewer = $this->viewer();
        $assignment = Assignment::factory()->create();

        $this->actingAs($viewer)->get("/assignments/{$assignment->id}/end")->assertForbidden();
        $this->actingAs($viewer)->patch("/assignments/{$assignment->id}/end", [])->assertForbidden();
    }

    public function test_ending_sets_status_selesai_and_keeps_is_current_true_with_no_new_row(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->create([
            'created_by' => $admin->id,
            'tanggal_mulai' => '2025-09-01',
        ]);

        $response = $this->actingAs($admin)->patch("/assignments/{$assignment->id}/end", [
            'tanggal_selesai' => '2025-09-15',
        ]);

        $response->assertRedirect(route('job-periods.show', $assignment->job_period_id));

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Selesai, $assignment->status);
        $this->assertTrue($assignment->is_current);
        $this->assertSame('2025-09-15', $assignment->tanggal_selesai->format('Y-m-d'));
        $this->assertSame(0, Assignment::where('previous_assignment_id', $assignment->id)->count());
    }

    public function test_tanggal_selesai_must_be_on_or_after_tanggal_mulai(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->create([
            'created_by' => $admin->id,
            'tanggal_mulai' => '2025-09-10',
        ]);

        $response = $this->actingAs($admin)->patch("/assignments/{$assignment->id}/end", [
            'tanggal_selesai' => '2025-09-01',
        ]);

        $response->assertSessionHasErrors('tanggal_selesai');
    }

    public function test_cannot_end_an_already_ended_assignment(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->selesai()->create();

        $this->actingAs($admin)->get("/assignments/{$assignment->id}/end")->assertNotFound();
    }
}
```

Run: `php artisan test --filter=AssignmentEndTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Format, run full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add app/Http/Requests/EndAssignmentRequest.php app/Http/Controllers/AssignmentController.php resources/js/Pages/Assignments/End.tsx routes/web.php resources/js/Pages/JobPeriods/Show.tsx tests/Feature/Assignments/AssignmentEndTest.php
git commit -m "Add Akhiri Penugasan action for ending an assignment without a successor"
```

---

### Task 6: Employee Detail Page with Riwayat Penugasan

**Files:**
- Modify: `app/Http/Controllers/EmployeeController.php` (add `show()`)
- Create: `resources/js/Pages/Employees/Show.tsx`
- Modify: `routes/web.php`
- Modify: `resources/js/types/index.d.ts`
- Modify: `resources/js/Pages/Employees/Index.tsx` (link rows to the new page)
- Test: `tests/Feature/Employees/EmployeeShowTest.php`

**Interfaces:**
- Consumes: `Employee::assignments()` (Task 1), `Assignment::jobPeriod()` (Task 1), `App\Support\Masks::partial()` (existing).
- Produces: route `employees.show` (GET `/employees/{employee}`); Inertia component `Employees/Show`.

- [ ] **Step 1: Add types**

In `resources/js/types/index.d.ts`, append:

```ts
export interface EmployeeAssignmentRow {
    id: number;
    job_nama_pekerjaan: string;
    no_dokumen: string;
    tanggal_mulai: string;
    tanggal_selesai: string | null;
    status: AssignmentStatus;
}
```

- [ ] **Step 2: Add `EmployeeController::show()`**

Add imports `App\Models\Assignment`. Add this method:

```php
    public function show(Request $request, Employee $employee): Response
    {
        $canManage = $request->user()->hasAnyRole(['admin', 'staff_input']);

        $assignments = $employee->assignments()->with('jobPeriod.job')->orderByDesc('tanggal_mulai')->get();

        return Inertia::render('Employees/Show', [
            'employee' => [
                'id' => $employee->id,
                'nama' => $employee->nama,
                'nik' => $canManage ? $employee->nik : Masks::partial($employee->nik),
                'alamat' => $employee->alamat,
                'no_rekening' => $canManage ? $employee->no_rekening : Masks::partial($employee->no_rekening),
                'nama_bank' => $employee->nama_bank,
                'status' => $employee->status->value,
            ],
            'assignments' => $assignments->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'job_nama_pekerjaan' => $assignment->jobPeriod->job->nama_pekerjaan,
                'no_dokumen' => $assignment->jobPeriod->no_dokumen,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $assignment->tanggal_selesai?->format('Y-m-d'),
                'status' => $assignment->status->value,
            ])->values(),
            'canManage' => $canManage,
        ]);
    }
```

- [ ] **Step 3: Register the route**

In `routes/web.php`, restructure the `employees` group so `show` sits between the first role-restricted block and the edit/toggle-status block (mirrors the `jobs` group exactly):

```php
Route::middleware('auth')->prefix('employees')->name('employees.')->group(function () {
    Route::get('/', [EmployeeController::class, 'index'])->name('index');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/', [EmployeeController::class, 'store'])->name('store');
        Route::get('/import', [EmployeeImportController::class, 'create'])->name('import.create');
        Route::post('/import', [EmployeeImportController::class, 'store'])->name('import.store');
        Route::get('/import/errors', [EmployeeImportController::class, 'downloadErrors'])->name('import.errors');
        Route::get('/export', [EmployeeExportController::class, 'download'])->name('export');
    });

    Route::get('/{employee}', [EmployeeController::class, 'show'])->name('show')->whereNumber('employee');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
        Route::patch('/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])->name('toggle-status');
    });
});
```

- [ ] **Step 4: Write the page component**

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EmployeeAssignmentRow, EmployeeDetail, PageProps } from '@/types';
import { Head } from '@inertiajs/react';

export default function Show({
    employee,
    assignments,
}: PageProps<{
    employee: EmployeeDetail;
    assignments: EmployeeAssignmentRow[];
    canManage: boolean;
}>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    {employee.nama}
                </h2>
            }
        >
            <Head title={employee.nama} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt className="text-slate-500">NIK</dt>
                                <dd className="font-medium">
                                    {employee.nik}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-slate-500">
                                    No. Rekening
                                </dt>
                                <dd className="font-medium">
                                    {employee.no_rekening}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-slate-500">Status</dt>
                                <dd className="font-medium">
                                    {employee.status === 'aktif'
                                        ? 'Aktif'
                                        : 'Non-aktif'}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="mb-4 text-sm font-semibold text-pln-navy">
                            Riwayat Penugasan
                        </h3>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Job
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            No. Dokumen
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Periode
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {assignments.map((assignment) => (
                                        <tr key={assignment.id}>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                                {
                                                    assignment.job_nama_pekerjaan
                                                }
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {assignment.no_dokumen}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {assignment.tanggal_mulai}
                                                {assignment.tanggal_selesai
                                                    ? ` s/d ${assignment.tanggal_selesai}`
                                                    : ''}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {assignment.status}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 5: Link rows in `Employees/Index.tsx`**

Wrap the `{employee.nama}` cell content in a `Link` to `employees.show`:

```tsx
<td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
    <Link
        href={route('employees.show', employee.id)}
        className="text-pln-blue hover:underline"
    >
        {employee.nama}
    </Link>
</td>
```

- [ ] **Step 6: Write the failing test, then implement until it passes**

```php
<?php

namespace Tests\Feature\Employees;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeShowTest extends TestCase
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

    public function test_page_renders_with_assignment_history(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['nama' => 'Siti']);
        $period = JobPeriod::factory()->create(['no_dokumen' => '12345']);
        Assignment::factory()->create([
            'employee_id' => $employee->id,
            'job_period_id' => $period->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/employees/{$employee->id}", $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'Employees/Show');
        $response->assertJsonPath('props.employee.nama', 'Siti');
        $response->assertJsonPath('props.assignments.0.no_dokumen', '12345');
    }

    public function test_viewer_sees_masked_nik(): void
    {
        $viewer = $this->viewer();
        $employee = Employee::factory()->create(['nik' => '1234567890123456']);

        $response = $this->actingAs($viewer)->get("/employees/{$employee->id}", $this->inertiaHeaders());

        $response->assertOk();
        $this->assertNotSame('1234567890123456', $response->json('props.employee.nik'));
    }
}
```

Run: `php artisan test --filter=EmployeeShowTest`
Expected: PASS (2 tests).

- [ ] **Step 7: Format, run full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add app/Http/Controllers/EmployeeController.php resources/js/Pages/Employees/Show.tsx routes/web.php resources/js/types/index.d.ts resources/js/Pages/Employees/Index.tsx tests/Feature/Employees/EmployeeShowTest.php
git commit -m "Add Employee detail page with riwayat penugasan"
```

---

### Task 7: Revise Job Scenario (c) to Redirect to JobPeriod Detail

**Files:**
- Modify: `app/Http/Controllers/JobController.php` (`renew()`)
- Modify: `resources/js/Pages/Jobs/Renew.tsx`
- Modify: `tests/Feature/Jobs/JobPeriodRenewalTest.php`

**Interfaces:**
- Consumes: `job-periods.show` route (Task 2), `Job::activePeriod()` (existing).
- Produces: `Jobs/Renew` prop `activePeriodId: number | null` (replaces the old inline-message state).

- [ ] **Step 1: Update `JobController::renew()`**

```php
    public function renew(Job $job): Response
    {
        return Inertia::render('Jobs/Renew', [
            'job' => [
                'id' => $job->id,
                'nama_pekerjaan' => $job->nama_pekerjaan,
            ],
            'activePeriodId' => $job->activePeriod?->id,
        ]);
    }
```

- [ ] **Step 2: Update `Renew.tsx`**

Replace the whole file with:

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface RenewJob {
    id: number;
    nama_pekerjaan: string;
}

export default function Renew({
    job,
    activePeriodId,
}: PageProps<{ job: RenewJob; activePeriodId: number | null }>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Perbarui PR: {job.nama_pekerjaan}
                </h2>
            }
        >
            <Head title={`Perbarui PR - ${job.nama_pekerjaan}`} />

            <div className="py-12">
                <div className="mx-auto max-w-2xl space-y-4 sm:px-6 lg:px-8">
                    <p className="text-sm text-slate-600">
                        Pilih salah satu skenario sesuai kondisi pembaruan
                        No. PR untuk pekerjaan ini:
                    </p>

                    <Link
                        href={route('jobs.periods.create', job.id)}
                        className="block rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-pln-blue"
                    >
                        <h3 className="text-sm font-semibold text-pln-navy">
                            (a) Lanjutan Job yang Sama
                        </h3>
                        <p className="mt-1 text-sm text-slate-600">
                            No. PR baru untuk pekerjaan yang sama —
                            periode lama otomatis ditutup dan
                            dihubungkan ke periode baru.
                        </p>
                    </Link>

                    <Link
                        href={route('jobs.create')}
                        className="block rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-pln-blue"
                    >
                        <h3 className="text-sm font-semibold text-pln-navy">
                            (b) Job Baru Sama Sekali
                        </h3>
                        <p className="mt-1 text-sm text-slate-600">
                            Pekerjaan yang benar-benar berbeda — dibuat
                            sebagai Job baru, tanpa relasi ke Job ini.
                        </p>
                    </Link>

                    {activePeriodId && (
                        <Link
                            href={route('job-periods.show', activePeriodId)}
                            className="block rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-pln-blue"
                        >
                            <h3 className="text-sm font-semibold text-pln-navy">
                                (c) Assignment Saja
                            </h3>
                            <p className="mt-1 text-sm text-slate-600">
                                Job & No. PR tidak berubah — kelola
                                penugasan pekerja untuk periode aktif
                                ini.
                            </p>
                        </Link>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 3: Update the renew-picker test**

In `tests/Feature/Jobs/JobPeriodRenewalTest.php`, replace `test_renew_picker_page_renders_for_a_job_with_an_active_period` with:

```php
    public function test_renew_picker_page_renders_with_active_period_id_for_scenario_c(): void
    {
        $admin = $this->admin();
        $job = Job::factory()->create(['nama_pekerjaan' => 'Mesin 2']);
        $period = JobPeriod::factory()->create(['job_id' => $job->id]);

        $response = $this->actingAs($admin)->get("/jobs/{$job->id}/renew", $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'Jobs/Renew');
        $response->assertJsonPath('props.job.nama_pekerjaan', 'Mesin 2');
        $response->assertJsonPath('props.activePeriodId', $period->id);
    }
```

- [ ] **Step 4: Run tests and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --filter=JobPeriodRenewalTest
```

Expected: PASS (all tests in the file, including the replaced one).

```bash
php artisan test --compact
git add app/Http/Controllers/JobController.php resources/js/Pages/Jobs/Renew.tsx tests/Feature/Jobs/JobPeriodRenewalTest.php
git commit -m "Redirect Job renew scenario (c) to the JobPeriod detail page"
```

---

### Task 8: Dashboard Assignment Count

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.tsx`
- Modify: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `Assignment`, `AssignmentStatus` (Task 1).
- Produces: Dashboard prop `assignmentCount: number`.

- [ ] **Step 1: Update `DashboardController`**

```php
<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\JobStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Job;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with a per-role user count summary.
     */
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'employeeCount' => Employee::count(),
            'jobCount' => Job::where('status', JobStatus::Aktif)->count(),
            'assignmentCount' => Assignment::where('is_current', true)
                ->where('status', AssignmentStatus::Aktif)
                ->count(),
            'roleCounts' => [
                'admin' => User::role(Role::Admin->value)->count(),
                'staff_input' => User::role(Role::StaffInput->value)->count(),
                'viewer' => User::role(Role::Viewer->value)->count(),
                'total' => User::count(),
            ],
        ]);
    }
}
```

- [ ] **Step 2: Update `Dashboard.tsx`**

Add `assignmentCount: number;` to the props type/destructure, and replace the "Assignment" `BentoCard` block with:

```tsx
<BentoCard title="Assignment" accent="yellow">
    <div className="text-2xl font-bold text-pln-navy">
        {assignmentCount}
    </div>
</BentoCard>
```

(Remove the `badge="Segera Hadir"` prop and the placeholder `—` content — this card is no longer a placeholder.)

- [ ] **Step 3: Write the failing test, then implement until it passes**

Add to `tests/Feature/DashboardTest.php`:

```php
    public function test_dashboard_shows_active_assignment_count(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Assignment::factory()->count(2)->create(['created_by' => $admin->id]);
        Assignment::factory()->selesai()->create(['created_by' => $admin->id]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('assignmentCount', 2)
        );
    }
```

Add the import `use App\Models\Assignment;` at the top of the file.

Run: `php artisan test --filter=DashboardTest`
Expected: PASS (4 tests).

- [ ] **Step 4: Format, run full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add app/Http/Controllers/DashboardController.php resources/js/Pages/Dashboard.tsx tests/Feature/DashboardTest.php
git commit -m "Wire real active-assignment count into the Dashboard"
```
