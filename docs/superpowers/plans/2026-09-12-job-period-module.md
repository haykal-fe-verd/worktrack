# Data Job & Periode PR Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Job (Pekerjaan) + JobPeriod (Periode PR) module to WorkTrack — CRUD, the 3-scenario "Perbarui PR" decision flow (Aturan Bisnis #2: lanjutan / job baru / assignment saja), pagination/search/filter, and Excel import/export — on top of the existing Auth & Role and Employee foundations.

**Architecture:** Two tables, `jobs` and `job_periods` (one-to-many, `job_periods.job_id`), with `job_periods.previous_period_id` self-referencing only for the "lanjutan" scenario. Reads (`jobs.index`, `jobs.show`) are open to any authenticated role; writes are gated to `admin`/`staff_input` via the same `role:admin|staff_input` middleware pattern used by the Employee module. Route ordering matters: literal-segment routes (`/jobs/create`, `/jobs/import`, `/jobs/export`) must be registered before the `/jobs/{job}` wildcard, or Laravel would try to resolve `{job}` model binding with `id = "create"` etc. Import reuses a small generalization of the Employee module's `ImportErrorsExport` (parameterized headings instead of hardcoded), since this is the second importer and the seam was already flagged as worth extracting once a second one landed.

**Tech Stack:** Laravel 13, PHP 8.4, `maatwebsite/excel` ^4.0 (already installed), Inertia.js + React + TypeScript, PHPUnit (class-based).

**Spec:** [docs/superpowers/specs/2026-09-12-job-period-design.md](../specs/2026-09-12-job-period-design.md) (parent spec: [docs/superpowers/specs/2026-09-09-worktrack-mvp-design.md](../specs/2026-09-09-worktrack-mvp-design.md) §6.3, §7, §8.2; sibling spec whose patterns this plan reuses: [docs/superpowers/specs/2026-09-11-employee-design.md](../specs/2026-09-11-employee-design.md))

## Global Constraints

- `no_dokumen` is unique **globally** across `job_periods`, regardless of `jenis_dokumen` (spec J5, §3.1).
- No permanent delete anywhere in the UI — Job/JobPeriod status changes only via toggle/business-rule transitions, never a delete route (matches the Employee module's pattern and PRD Asumsi A8).
- The "lanjutan vs job baru vs assignment saja" decision (Aturan Bisnis #2) is **never automated** — always an explicit staff choice in the UI (spec §3.3, PRD Asumsi A2). Import never tries to match a row to an existing Job (spec §3.4.3) — every valid imported row creates a brand-new Job + JobPeriod.
- `jumlah_tk_rencana` is stored but has no validation/warning logic against Assignment counts in this plan — that's deferred to the future Assignment sub-project (spec J4).
- Job status (`aktif`/`selesai`) changes only via a toggle action, never a free field in the Job edit form (spec J6) — mirrors how Employee status works.
- Import: valid rows save immediately; malformed rows or duplicate `no_dokumen` go into a downloadable Excel error report generated from in-memory data — no database table, no queue (spec §3.4.5, matching the Employee module's constraint).
- Export is full-dataset, restricted to `admin`/`staff_input` only (spec §3.5) — no masking needed (Job/JobPeriod data isn't PII).
- List pagination: 20 per page, with search and status filter (spec §4).
- All commands run via Docker: `docker compose exec app <command>` for PHP/artisan/Pint, the `docker run ... node:20-alpine` one-shot for `npm run build`.
- After any PHP file change, run `vendor/bin/pint --dirty --format agent` before committing (project convention, `CLAUDE.md`).
- Tests that GET an Inertia page whose `.tsx` component doesn't exist yet (because a later task in this plan builds it) must send `X-Inertia: true` + a matching `X-Inertia-Version` header, exactly as `tests/Feature/Employees/EmployeeCrudTest.php`'s `inertiaHeaders()` helper does — otherwise the test fails with a Vite manifest error, not an application bug.

---

### Task 1: Data foundation — migrations, enums, models, factories

**Files:**
- Create: `database/migrations/2026_09_12_000001_create_jobs_table.php`
- Create: `database/migrations/2026_09_12_000002_create_job_periods_table.php`
- Create: `app/Enums/JobStatus.php`
- Create: `app/Enums/JobPeriodStatus.php`
- Create: `app/Enums/DocumentType.php`
- Create: `app/Models/Job.php`
- Create: `app/Models/JobPeriod.php`
- Create: `database/factories/JobFactory.php`
- Create: `database/factories/JobPeriodFactory.php`
- Test: `tests/Unit/JobPeriodModelTest.php`

**Interfaces:**
- Produces: `Job` model with `periods()` (HasMany JobPeriod) relation; `JobPeriod` model with `job()` (BelongsTo Job) and `previousPeriod()` (BelongsTo JobPeriod, nullable) relations, `status` cast to `JobPeriodStatus`, `jenis_dokumen` cast to `DocumentType`. `Job::status` cast to `JobStatus`. `JobFactory`/`JobPeriodFactory` for tests in Tasks 2, 4, 6. All of Task 2-8 build on these exact class/column names.

- [ ] **Step 1: Write the failing test for the Job/JobPeriod relationship**

```php
<?php

namespace Tests\Unit;

use App\Enums\DocumentType;
use App\Enums\JobPeriodStatus;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPeriodModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_job_has_many_periods(): void
    {
        $job = Job::factory()->create();
        JobPeriod::factory()->count(2)->create(['job_id' => $job->id]);

        $this->assertCount(2, $job->periods);
    }

    public function test_a_period_belongs_to_a_job(): void
    {
        $job = Job::factory()->create();
        $period = JobPeriod::factory()->create(['job_id' => $job->id]);

        $this->assertTrue($period->job->is($job));
    }

    public function test_a_period_can_reference_its_previous_period(): void
    {
        $job = Job::factory()->create();
        $oldPeriod = JobPeriod::factory()->create(['job_id' => $job->id]);
        $newPeriod = JobPeriod::factory()->create([
            'job_id' => $job->id,
            'previous_period_id' => $oldPeriod->id,
        ]);

        $this->assertTrue($newPeriod->previousPeriod->is($oldPeriod));
    }

    public function test_status_and_document_type_are_cast_to_enums(): void
    {
        $job = Job::factory()->create();
        $period = JobPeriod::factory()->create([
            'job_id' => $job->id,
            'jenis_dokumen' => DocumentType::PR,
            'status' => JobPeriodStatus::Aktif,
        ]);

        $this->assertInstanceOf(JobStatus::class, $job->status);
        $this->assertInstanceOf(DocumentType::class, $period->jenis_dokumen);
        $this->assertInstanceOf(JobPeriodStatus::class, $period->status);
        $this->assertSame(DocumentType::PR, $period->jenis_dokumen);
    }
}
```

Save this to `tests/Unit/JobPeriodModelTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=JobPeriodModelTest`
Expected: FAIL — `Class "App\Models\Job" not found`.

- [ ] **Step 3: Create the enums**

```php
<?php

namespace App\Enums;

enum JobStatus: string
{
    case Aktif = 'aktif';
    case Selesai = 'selesai';
}
```

Save this to `app/Enums/JobStatus.php`.

```php
<?php

namespace App\Enums;

enum JobPeriodStatus: string
{
    case Aktif = 'aktif';
    case Berakhir = 'berakhir';
    case Diperbarui = 'diperbarui';
}
```

Save this to `app/Enums/JobPeriodStatus.php`. Note: only `Aktif` and `Berakhir` are ever set by business logic in this plan (see spec §3.3) — `Diperbarui` exists to match the parent spec's data model (§6.3) but is reserved for a future module's use, not exercised here.

```php
<?php

namespace App\Enums;

enum DocumentType: string
{
    case PR = 'PR';
    case PO = 'PO';
    case DO = 'DO';
    case WO = 'WO';
}
```

Save this to `app/Enums/DocumentType.php`.

- [ ] **Step 4: Create the migrations**

Run: `docker compose exec app php artisan make:migration create_jobs_table --no-interaction`

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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('nama_pekerjaan');
            $table->string('lokasi')->nullable();
            $table->string('klien')->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
```

Run: `docker compose exec app php artisan make:migration create_job_periods_table --no-interaction`

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
        Schema::create('job_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('jenis_dokumen', 10);
            $table->string('no_dokumen', 50)->unique();
            $table->string('kode_po', 50)->nullable();
            $table->decimal('nilai_po', 15, 2);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->integer('jumlah_tk_rencana');
            $table->string('status', 20)->default('aktif');
            $table->foreignId('previous_period_id')->nullable()->constrained('job_periods')->nullOnDelete();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_periods');
    }
};
```

- [ ] **Step 5: Create the models**

```php
<?php

namespace App\Models;

use App\Enums\JobStatus;
use Database\Factories\JobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_pekerjaan', 'lokasi', 'klien', 'status'])]
class Job extends Model
{
    /** @use HasFactory<JobFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
        ];
    }

    /**
     * @return HasMany<JobPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(JobPeriod::class);
    }
}
```

Save this to `app/Models/Job.php`.

```php
<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\JobPeriodStatus;
use Database\Factories\JobPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'job_id',
    'jenis_dokumen',
    'no_dokumen',
    'kode_po',
    'nilai_po',
    'tanggal_mulai',
    'tanggal_selesai',
    'jumlah_tk_rencana',
    'status',
    'previous_period_id',
    'keterangan',
])]
class JobPeriod extends Model
{
    /** @use HasFactory<JobPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'jenis_dokumen' => DocumentType::class,
            'status' => JobPeriodStatus::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'nilai_po' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * @return BelongsTo<JobPeriod, $this>
     */
    public function previousPeriod(): BelongsTo
    {
        return $this->belongsTo(JobPeriod::class, 'previous_period_id');
    }
}
```

Save this to `app/Models/JobPeriod.php`.

- [ ] **Step 6: Create the factories**

```php
<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\Job;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    protected $model = Job::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_pekerjaan' => fake()->words(3, true),
            'lokasi' => fake()->city(),
            'klien' => 'PLN '.fake()->randomElement(['Unit A', 'Unit B', 'Unit C']),
            'status' => JobStatus::Aktif,
        ];
    }

    public function selesai(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobStatus::Selesai,
        ]);
    }
}
```

Save this to `database/factories/JobFactory.php`.

```php
<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Enums\JobPeriodStatus;
use App\Models\Job;
use App\Models\JobPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobPeriod>
 */
class JobPeriodFactory extends Factory
{
    protected $model = JobPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_id' => Job::factory(),
            'jenis_dokumen' => DocumentType::PR,
            'no_dokumen' => fake()->unique()->numerify('#####'),
            'kode_po' => fake()->bothify('PTC##?'),
            'nilai_po' => fake()->randomFloat(2, 1000000, 100000000),
            'tanggal_mulai' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'tanggal_selesai' => null,
            'jumlah_tk_rencana' => fake()->numberBetween(1, 30),
            'status' => JobPeriodStatus::Aktif,
        ];
    }

    public function berakhir(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => JobPeriodStatus::Berakhir,
        ]);
    }
}
```

Save this to `database/factories/JobPeriodFactory.php`.

- [ ] **Step 7: Run the migrations and the full test suite**

Run: `docker compose exec app php artisan migrate`
Expected: both new migrations marked `DONE`.

Run: `docker compose exec app php artisan test`
Expected: all existing tests still pass, plus the 4 new `JobPeriodModelTest` tests (74 + 4 = 78).

- [ ] **Step 8: Format and commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add database/migrations/2026_09_12_000001_create_jobs_table.php database/migrations/2026_09_12_000002_create_job_periods_table.php app/Enums/JobStatus.php app/Enums/JobPeriodStatus.php app/Enums/DocumentType.php app/Models/Job.php app/Models/JobPeriod.php database/factories/JobFactory.php database/factories/JobPeriodFactory.php tests/Unit/JobPeriodModelTest.php
git commit -m "Add Job/JobPeriod data foundation (migrations, models, enums, factories)"
```

---

### Task 2: Job CRUD backend (create, index, show, toggle status)

**Files:**
- Create: `app/Http/Requests/StoreJobRequest.php`
- Create: `app/Http/Controllers/JobController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Jobs/JobCrudTest.php`

**Interfaces:**
- Consumes: `Job`/`JobPeriod` models, `JobStatus`/`JobPeriodStatus`/`DocumentType` enums (Task 1).
- Produces: routes `jobs.index` (GET `/jobs`), `jobs.create` (GET `/jobs/create`), `jobs.store` (POST `/jobs`), `jobs.show` (GET `/jobs/{job}`), `jobs.toggle-status` (PATCH `/jobs/{job}/toggle-status`) — Task 3's frontend and Task 4's routes both build on these. `Inertia::render('Jobs/Index', ['jobs' => <paginator>, 'filters' => [...], 'canManage' => bool])` where each job row is `{id, nama_pekerjaan, klien, lokasi, status, periods_count}`. `Inertia::render('Jobs/Create')` (no props). `Inertia::render('Jobs/Show', ['job' => {id, nama_pekerjaan, lokasi, klien, status}, 'periods' => [{id, jenis_dokumen, no_dokumen, kode_po, nilai_po, tanggal_mulai, tanggal_selesai, jumlah_tk_rencana, status}], 'hasActivePeriod' => bool, 'canManage' => bool])` — Task 4/5 rely on `hasActivePeriod` to decide whether to show the "Perbarui PR" button.

**IMPORTANT route ordering:** register `/jobs/create` (and, in Task 4, `/jobs/import`, `/jobs/export`) **before** `/jobs/{job}`, or Laravel will try to model-bind `{job}` with the literal string `"create"`/`"import"`/`"export"` and fail. See this task's Step 5 for the exact structure.

- [ ] **Step 1: Write the failing test**

```php
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

        $response->assertRedirect(route('jobs.index'));
        $this->assertDatabaseHas('jobs', ['nama_pekerjaan' => 'Siaga Ubur Ubur', 'status' => 'aktif']);
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

        $response->assertRedirect(route('jobs.index'));
        $this->assertDatabaseHas('jobs', ['nama_pekerjaan' => 'Helper Gudang']);
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
            ->assertRedirect(route('jobs.index'));

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
```

Save this to `tests/Feature/Jobs/JobCrudTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=JobCrudTest`
Expected: FAIL — no `/jobs` routes exist yet.

- [ ] **Step 3: Create the form request**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
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
            'nama_pekerjaan' => ['required', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'klien' => ['nullable', 'string', 'max:255'],
            'jenis_dokumen' => ['required', 'string', 'in:PR,PO,DO,WO'],
            'no_dokumen' => ['required', 'string', 'max:50', 'unique:job_periods,no_dokumen'],
            'kode_po' => ['nullable', 'string', 'max:50'],
            'nilai_po' => ['required', 'numeric', 'min:0'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'jumlah_tk_rencana' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Enums\JobPeriodStatus;
use App\Enums\JobStatus;
use App\Http\Requests\StoreJobRequest;
use App\Models\Job;
use App\Models\JobPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request): Response
    {
        $canManage = $request->user()->hasAnyRole(['admin', 'staff_input']);

        $jobs = Job::query()
            ->withCount('periods')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($query) use ($search) {
                    $query->where('nama_pekerjaan', 'like', "%{$search}%")
                        ->orWhere('klien', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('nama_pekerjaan')
            ->paginate(20)
            ->withQueryString();

        $jobs->through(fn (Job $job) => [
            'id' => $job->id,
            'nama_pekerjaan' => $job->nama_pekerjaan,
            'klien' => $job->klien,
            'lokasi' => $job->lokasi,
            'status' => $job->status->value,
            'periods_count' => $job->periods_count,
        ]);

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'filters' => $request->only(['search', 'status']),
            'canManage' => $canManage,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Jobs/Create');
    }

    public function store(StoreJobRequest $request): RedirectResponse
    {
        $job = Job::create([
            'nama_pekerjaan' => $request->validated('nama_pekerjaan'),
            'lokasi' => $request->validated('lokasi'),
            'klien' => $request->validated('klien'),
            'status' => JobStatus::Aktif,
        ]);

        $job->periods()->create([
            'jenis_dokumen' => $request->validated('jenis_dokumen'),
            'no_dokumen' => $request->validated('no_dokumen'),
            'kode_po' => $request->validated('kode_po'),
            'nilai_po' => $request->validated('nilai_po'),
            'tanggal_mulai' => $request->validated('tanggal_mulai'),
            'tanggal_selesai' => $request->validated('tanggal_selesai'),
            'jumlah_tk_rencana' => $request->validated('jumlah_tk_rencana'),
            'keterangan' => $request->validated('keterangan'),
            'status' => JobPeriodStatus::Aktif,
        ]);

        return redirect()->route('jobs.index');
    }

    public function show(Request $request, Job $job): Response
    {
        $periods = $job->periods()->orderByDesc('tanggal_mulai')->get();

        return Inertia::render('Jobs/Show', [
            'job' => [
                'id' => $job->id,
                'nama_pekerjaan' => $job->nama_pekerjaan,
                'lokasi' => $job->lokasi,
                'klien' => $job->klien,
                'status' => $job->status->value,
            ],
            'periods' => $periods->map(fn (JobPeriod $period) => [
                'id' => $period->id,
                'jenis_dokumen' => $period->jenis_dokumen->value,
                'no_dokumen' => $period->no_dokumen,
                'kode_po' => $period->kode_po,
                'nilai_po' => (float) $period->nilai_po,
                'tanggal_mulai' => $period->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $period->tanggal_selesai?->format('Y-m-d'),
                'jumlah_tk_rencana' => $period->jumlah_tk_rencana,
                'status' => $period->status->value,
            ])->values(),
            'hasActivePeriod' => $job->periods()->where('status', JobPeriodStatus::Aktif)->exists(),
            'canManage' => $request->user()->hasAnyRole(['admin', 'staff_input']),
        ]);
    }

    public function toggleStatus(Job $job): RedirectResponse
    {
        $job->update([
            'status' => $job->status === JobStatus::Aktif ? JobStatus::Selesai : JobStatus::Aktif,
        ]);

        return redirect()->route('jobs.index');
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, add the import near the top:

```php
use App\Http\Controllers\JobController;
```

Then add this block **after** the existing `employees` group and **before** `require __DIR__.'/auth.php';`. Pay close attention to the ordering within the block — literal segments first, `{job}` wildcard second, exactly as shown:

```php
Route::middleware('auth')->prefix('jobs')->name('jobs.')->group(function () {
    Route::get('/', [JobController::class, 'index'])->name('index');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/create', [JobController::class, 'create'])->name('create');
        Route::post('/', [JobController::class, 'store'])->name('store');
    });

    Route::get('/{job}', [JobController::class, 'show'])->name('show');

    Route::middleware('role:admin|staff_input')->group(function () {
        Route::patch('/{job}/toggle-status', [JobController::class, 'toggleStatus'])->name('toggle-status');
    });
});
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=JobCrudTest`
Expected: PASS (11/11).

- [ ] **Step 7: Run the full test suite**

Run: `docker compose exec app php artisan test`
Expected: all tests pass (78 + 11 = 89).

- [ ] **Step 8: Format and commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Http/Requests/StoreJobRequest.php app/Http/Controllers/JobController.php routes/web.php tests/Feature/Jobs/JobCrudTest.php
git commit -m "Add Job CRUD backend (create with first period, index, show, toggle status)"
```

---

### Task 3: Job CRUD frontend

**Files:**
- Create: `resources/js/Pages/Jobs/Index.tsx`
- Create: `resources/js/Pages/Jobs/Create.tsx`
- Create: `resources/js/Pages/Jobs/Show.tsx`
- Modify: `resources/js/types/index.d.ts`
- Modify: `resources/js/Layouts/AuthenticatedLayout.tsx`

**Interfaces:**
- Consumes: `jobs.index`/`jobs.create`/`jobs.store`/`jobs.show`/`jobs.toggle-status` routes and prop shapes (Task 2).
- Produces: `JobRow`, `JobDetail`, `JobPeriodRow` TS types — Tasks 5 and 7 build pages that link to/from `Jobs/Show.tsx`.

**Deliberate scope limit, same reasoning as the Employee module's Task 3:** `Jobs/Show.tsx` must NOT link to `jobs.renew`, `jobs.import.create`, or `jobs.export` yet — those routes don't exist until Tasks 4 and 6. Ziggy's `route()` throws immediately at render time for an unregistered name. Tasks 5 and 6 add those links in the same task that registers the route.

- [ ] **Step 1: Add shared TypeScript types**

In `resources/js/types/index.d.ts`, add (keep everything already there untouched):

```ts
export type JobStatus = 'aktif' | 'selesai';
export type JobPeriodStatus = 'aktif' | 'berakhir' | 'diperbarui';
export type DocumentType = 'PR' | 'PO' | 'DO' | 'WO';

export interface JobRow {
    id: number;
    nama_pekerjaan: string;
    klien: string | null;
    lokasi: string | null;
    status: JobStatus;
    periods_count: number;
}

export interface JobDetail {
    id: number;
    nama_pekerjaan: string;
    lokasi: string | null;
    klien: string | null;
    status: JobStatus;
}

export interface JobPeriodRow {
    id: number;
    jenis_dokumen: DocumentType;
    no_dokumen: string;
    kode_po: string | null;
    nilai_po: number;
    tanggal_mulai: string;
    tanggal_selesai: string | null;
    jumlah_tk_rencana: number;
    status: JobPeriodStatus;
}
```

- [ ] **Step 2: Create the Jobs index page**

```tsx
import Pagination from '@/Components/Pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { JobRow, PageProps, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Filters {
    search?: string;
    status?: string;
}

export default function Index({
    jobs,
    filters,
    canManage,
}: PageProps<{
    jobs: Paginated<JobRow>;
    filters: Filters;
    canManage: boolean;
}>) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilters: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            route('jobs.index'),
            { search, status },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title="Data Job & Periode PR" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form
                            onSubmit={applyFilters}
                            className="mb-4 flex flex-wrap items-end gap-3"
                        >
                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Cari nama/klien
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
                                    <option value="selesai">Selesai</option>
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
                                        href={route('jobs.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Job
                                    </Link>
                                </div>
                            )}
                        </form>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Nama Pekerjaan
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Klien
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Lokasi
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Periode PR
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {jobs.data.map((job) => (
                                        <tr key={job.id}>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                                <Link
                                                    href={route(
                                                        'jobs.show',
                                                        job.id,
                                                    )}
                                                    className="text-pln-blue hover:text-pln-blue-dark"
                                                >
                                                    {job.nama_pekerjaan}
                                                </Link>
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {job.klien ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {job.lokasi ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {job.periods_count}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {job.status === 'aktif'
                                                    ? 'Aktif'
                                                    : 'Selesai'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Pagination links={jobs.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

Save this to `resources/js/Pages/Jobs/Index.tsx`.

- [ ] **Step 3: Create the Jobs create page**

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
        nama_pekerjaan: '',
        lokasi: '',
        klien: '',
        jenis_dokumen: 'PR',
        no_dokumen: '',
        kode_po: '',
        nilai_po: '',
        tanggal_mulai: '',
        tanggal_selesai: '',
        jumlah_tk_rencana: '',
        keterangan: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('jobs.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Tambah Job
                </h2>
            }
        >
            <Head title="Tambah Job" />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <h3 className="text-sm font-semibold text-pln-navy">
                                Data Job
                            </h3>

                            <div className="mt-3">
                                <InputLabel
                                    htmlFor="nama_pekerjaan"
                                    value="Nama Pekerjaan"
                                />
                                <TextInput
                                    id="nama_pekerjaan"
                                    className="mt-1 block w-full"
                                    value={data.nama_pekerjaan}
                                    onChange={(e) =>
                                        setData(
                                            'nama_pekerjaan',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    isFocused
                                />
                                <InputError
                                    message={errors.nama_pekerjaan}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel htmlFor="klien" value="Klien" />
                                <TextInput
                                    id="klien"
                                    className="mt-1 block w-full"
                                    value={data.klien}
                                    onChange={(e) =>
                                        setData('klien', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.klien}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="lokasi"
                                    value="Lokasi"
                                />
                                <TextInput
                                    id="lokasi"
                                    className="mt-1 block w-full"
                                    value={data.lokasi}
                                    onChange={(e) =>
                                        setData('lokasi', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.lokasi}
                                    className="mt-2"
                                />
                            </div>

                            <h3 className="mt-6 text-sm font-semibold text-pln-navy">
                                Periode PR Pertama
                            </h3>

                            <div className="mt-3">
                                <InputLabel
                                    htmlFor="jenis_dokumen"
                                    value="Jenis Dokumen"
                                />
                                <select
                                    id="jenis_dokumen"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.jenis_dokumen}
                                    onChange={(e) =>
                                        setData(
                                            'jenis_dokumen',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="PR">PR</option>
                                    <option value="PO">PO</option>
                                    <option value="DO">DO</option>
                                    <option value="WO">WO</option>
                                </select>
                                <InputError
                                    message={errors.jenis_dokumen}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="no_dokumen"
                                    value="No. Dokumen"
                                />
                                <TextInput
                                    id="no_dokumen"
                                    className="mt-1 block w-full"
                                    value={data.no_dokumen}
                                    onChange={(e) =>
                                        setData(
                                            'no_dokumen',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.no_dokumen}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="kode_po"
                                    value="Kode PO (opsional)"
                                />
                                <TextInput
                                    id="kode_po"
                                    className="mt-1 block w-full"
                                    value={data.kode_po}
                                    onChange={(e) =>
                                        setData('kode_po', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.kode_po}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="nilai_po"
                                    value="Nilai PO"
                                />
                                <TextInput
                                    id="nilai_po"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.nilai_po}
                                    onChange={(e) =>
                                        setData('nilai_po', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.nilai_po}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4 grid grid-cols-2 gap-3">
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

                                <div>
                                    <InputLabel
                                        htmlFor="tanggal_selesai"
                                        value="Tanggal Selesai"
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
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="jumlah_tk_rencana"
                                    value="Jumlah TK Rencana"
                                />
                                <TextInput
                                    id="jumlah_tk_rencana"
                                    type="number"
                                    className="mt-1 block w-full"
                                    value={data.jumlah_tk_rencana}
                                    onChange={(e) =>
                                        setData(
                                            'jumlah_tk_rencana',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.jumlah_tk_rencana}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="keterangan"
                                    value="Keterangan (opsional)"
                                />
                                <textarea
                                    id="keterangan"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.keterangan}
                                    onChange={(e) =>
                                        setData(
                                            'keterangan',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.keterangan}
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

Save this to `resources/js/Pages/Jobs/Create.tsx`.

- [ ] **Step 4: Create the Jobs show page**

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { JobDetail, JobPeriodRow, PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';

export default function Show({
    job,
    periods,
    hasActivePeriod,
    canManage,
}: PageProps<{
    job: JobDetail;
    periods: JobPeriodRow[];
    hasActivePeriod: boolean;
    canManage: boolean;
}>) {
    const toggleStatus = () => {
        router.patch(route('jobs.toggle-status', job.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    {job.nama_pekerjaan}
                </h2>
            }
        >
            <Head title={job.nama_pekerjaan} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt className="text-slate-500">Klien</dt>
                                <dd className="font-medium">
                                    {job.klien ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-slate-500">Lokasi</dt>
                                <dd className="font-medium">
                                    {job.lokasi ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-slate-500">Status</dt>
                                <dd className="font-medium">
                                    {job.status === 'aktif'
                                        ? 'Aktif'
                                        : 'Selesai'}
                                </dd>
                            </div>
                        </dl>

                        {canManage && (
                            <div className="mt-4 flex gap-2">
                                <button
                                    type="button"
                                    onClick={toggleStatus}
                                    className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                >
                                    {job.status === 'aktif'
                                        ? 'Tandai Selesai'
                                        : 'Tandai Aktif'}
                                </button>
                            </div>
                        )}
                    </div>

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-pln-navy">
                                Riwayat Periode PR
                            </h3>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Jenis
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            No. Dokumen
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Periode
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Nilai PO
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {periods.map((period) => (
                                        <tr key={period.id}>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                                {period.jenis_dokumen}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {period.no_dokumen}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {period.tanggal_mulai}
                                                {period.tanggal_selesai
                                                    ? ` s/d ${period.tanggal_selesai}`
                                                    : ''}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {period.nilai_po.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {period.status}
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

Save this to `resources/js/Pages/Jobs/Show.tsx`. Note: `hasActivePeriod` is accepted as a prop but not used yet — Task 5 adds the "Perbarui PR" button gated on it, once `jobs.renew` exists.

- [ ] **Step 5: Add a "Job & PR" nav link visible to every authenticated role**

In `resources/js/Layouts/AuthenticatedLayout.tsx`, the desktop nav currently reads (after Task 3 of the Employee module added "Karyawan"):

```tsx
                                <NavLink
                                    href={route('employees.index')}
                                    active={route().current('employees.*')}
                                >
                                    Karyawan
                                </NavLink>
                                {user.roles.includes('admin') && (
```

Change it to insert a new link right after "Karyawan":

```tsx
                                <NavLink
                                    href={route('employees.index')}
                                    active={route().current('employees.*')}
                                >
                                    Karyawan
                                </NavLink>
                                <NavLink
                                    href={route('jobs.index')}
                                    active={route().current('jobs.*')}
                                >
                                    Job & PR
                                </NavLink>
                                {user.roles.includes('admin') && (
```

Do the equivalent for the mobile nav block, which mirrors the desktop one with `ResponsiveNavLink` right after the "Karyawan" `ResponsiveNavLink`:

```tsx
                        <ResponsiveNavLink
                            href={route('employees.index')}
                            active={route().current('employees.*')}
                        >
                            Karyawan
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            href={route('jobs.index')}
                            active={route().current('jobs.*')}
                        >
                            Job & PR
                        </ResponsiveNavLink>
                        {user.roles.includes('admin') && (
```

- [ ] **Step 6: Rebuild frontend assets**

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20-alpine sh -c "npm run build"
```

Expected: 0 TypeScript errors.

- [ ] **Step 7: Run the full backend test suite**

Run: `docker compose exec app php artisan test`
Expected: all 89 tests still pass.

- [ ] **Step 8: Manual browser verification**

1. Log in as a `viewer`. Confirm a "Job & PR" nav link appears, and clicking it shows the (empty, or seeded) job list with no "Tambah Job" button.
2. Log in as an `admin`. Confirm "Tambah Job" renders. Click it, fill the form (Job fields + first period fields), submit — confirm redirect to the list and the new job appears with the right `periods_count` (1).
3. Click the job's name to open its detail page — confirm the Job info and the one period row render correctly, and the "Tandai Selesai" button works (toggles status, page reflects the change).

- [ ] **Step 9: Commit**

```bash
git add resources/js/Pages/Jobs resources/js/types/index.d.ts resources/js/Layouts/AuthenticatedLayout.tsx
git commit -m "Add Job CRUD frontend pages and nav link"
```

---

### Task 4: Perbarui PR backend (renewal decision flow, scenario a)

**Files:**
- Create: `app/Http/Requests/StoreJobPeriodRequest.php`
- Create: `app/Http/Controllers/JobPeriodController.php`
- Modify: `app/Http/Controllers/JobController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Jobs/JobPeriodRenewalTest.php`

**Interfaces:**
- Consumes: `Job`/`JobPeriod` models (Task 1), `jobs.show`'s `hasActivePeriod` prop (Task 2/3, already computed — this task doesn't change that computation, just adds routes that react to it).
- Produces: routes `jobs.renew` (GET `/jobs/{job}/renew`, the 3-scenario picker page), `jobs.periods.create` (GET `/jobs/{job}/periods/create`, scenario (a) form), `jobs.periods.store` (POST `/jobs/{job}/periods`, scenario (a) submission). `Inertia::render('Jobs/Renew', ['job' => {id, nama_pekerjaan}])`. `Inertia::render('Jobs/Periods/Create', ['job' => {id, nama_pekerjaan}])`. Scenario (b) has NO dedicated backend route — it reuses `jobs.create` directly (spec §4: "reuse form Create Job"). Scenario (c) has NO backend route at all — it's a pure frontend message (Task 5).

- [ ] **Step 1: Write the failing test**

```php
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
}
```

Save this to `tests/Feature/Jobs/JobPeriodRenewalTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=JobPeriodRenewalTest`
Expected: FAIL — `/jobs/{job}/renew` and related routes don't exist yet.

- [ ] **Step 3: Create the form request**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobPeriodRequest extends FormRequest
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
            'jenis_dokumen' => ['required', 'string', 'in:PR,PO,DO,WO'],
            'no_dokumen' => ['required', 'string', 'max:50', 'unique:job_periods,no_dokumen'],
            'kode_po' => ['nullable', 'string', 'max:50'],
            'nilai_po' => ['required', 'numeric', 'min:0'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'jumlah_tk_rencana' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
        ];
    }
}
```

- [ ] **Step 4: Create the JobPeriodController**

```php
<?php

namespace App\Http\Controllers;

use App\Enums\JobPeriodStatus;
use App\Http\Requests\StoreJobPeriodRequest;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class JobPeriodController extends Controller
{
    public function create(Job $job): Response
    {
        return Inertia::render('Jobs/Periods/Create', [
            'job' => [
                'id' => $job->id,
                'nama_pekerjaan' => $job->nama_pekerjaan,
            ],
        ]);
    }

    public function store(StoreJobPeriodRequest $request, Job $job): RedirectResponse
    {
        $oldPeriod = $job->periods()->where('status', JobPeriodStatus::Aktif)->first();

        $job->periods()->create([
            ...$request->validated(),
            'status' => JobPeriodStatus::Aktif,
            'previous_period_id' => $oldPeriod?->id,
        ]);

        if ($oldPeriod) {
            $oldPeriod->update(['status' => JobPeriodStatus::Berakhir]);
        }

        return redirect()->route('jobs.show', $job);
    }
}
```

- [ ] **Step 5: Add the `renew` method to `JobController`**

In `app/Http/Controllers/JobController.php`, add this method (anywhere inside the class, e.g. right after `show()`):

```php
    public function renew(Job $job): Response
    {
        return Inertia::render('Jobs/Renew', [
            'job' => [
                'id' => $job->id,
                'nama_pekerjaan' => $job->nama_pekerjaan,
            ],
        ]);
    }
```

- [ ] **Step 6: Register the routes**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\JobPeriodController;
```

Add these routes inside the SECOND `role:admin|staff_input` sub-group under the `jobs` prefix (the one already containing `toggle-status`, registered after the `/{job}` show route):

```php
    Route::middleware('role:admin|staff_input')->group(function () {
        Route::patch('/{job}/toggle-status', [JobController::class, 'toggleStatus'])->name('toggle-status');
        Route::get('/{job}/renew', [JobController::class, 'renew'])->name('renew');
        Route::get('/{job}/periods/create', [JobPeriodController::class, 'create'])->name('periods.create');
        Route::post('/{job}/periods', [JobPeriodController::class, 'store'])->name('periods.store');
    });
```

- [ ] **Step 7: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=JobPeriodRenewalTest`
Expected: PASS (5/5).

- [ ] **Step 8: Run the full test suite**

Run: `docker compose exec app php artisan test`
Expected: all tests pass (89 + 5 = 94).

- [ ] **Step 9: Format and commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Http/Requests/StoreJobPeriodRequest.php app/Http/Controllers/JobPeriodController.php app/Http/Controllers/JobController.php routes/web.php tests/Feature/Jobs/JobPeriodRenewalTest.php
git commit -m "Add Perbarui PR backend (scenario a: continuation under the same job)"
```

---

### Task 5: Perbarui PR frontend (scenario picker + scenario a form)

**Files:**
- Create: `resources/js/Pages/Jobs/Renew.tsx`
- Create: `resources/js/Pages/Jobs/Periods/Create.tsx`
- Modify: `resources/js/Pages/Jobs/Show.tsx`

**Interfaces:**
- Consumes: `jobs.renew`/`jobs.periods.create`/`jobs.periods.store`/`jobs.create` routes (Task 4, and `jobs.create` from Task 2).
- Produces: nothing later tasks in this plan depend on.

- [ ] **Step 1: Create the Renew (scenario picker) page**

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface RenewJob {
    id: number;
    nama_pekerjaan: string;
}

export default function Renew({ job }: PageProps<{ job: RenewJob }>) {
    const [showAssignmentNote, setShowAssignmentNote] = useState(false);

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

                    <button
                        type="button"
                        onClick={() => setShowAssignmentNote(true)}
                        className="block w-full rounded-lg border border-slate-200 bg-white p-4 text-left shadow-sm hover:border-pln-blue"
                    >
                        <h3 className="text-sm font-semibold text-pln-navy">
                            (c) Assignment Saja
                        </h3>
                        <p className="mt-1 text-sm text-slate-600">
                            Job & No. PR tidak berubah, hanya penugasan
                            pekerja yang berbeda.
                        </p>
                    </button>

                    {showAssignmentNote && (
                        <div className="rounded-lg bg-pln-blue/10 p-4 text-sm text-pln-navy">
                            Job & Periode PR tidak berubah — kelola
                            penugasan pekerja di modul Assignment.
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

Save this to `resources/js/Pages/Jobs/Renew.tsx`.

- [ ] **Step 2: Create the scenario (a) form page**

```tsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface RenewJob {
    id: number;
    nama_pekerjaan: string;
}

export default function Create({ job }: PageProps<{ job: RenewJob }>) {
    const { data, setData, post, processing, errors } = useForm({
        jenis_dokumen: 'PR',
        no_dokumen: '',
        kode_po: '',
        nilai_po: '',
        tanggal_mulai: '',
        tanggal_selesai: '',
        jumlah_tk_rencana: '',
        keterangan: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('jobs.periods.store', job.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Periode PR Baru: {job.nama_pekerjaan}
                </h2>
            }
        >
            <Head title={`Periode PR Baru - ${job.nama_pekerjaan}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel
                                    htmlFor="jenis_dokumen"
                                    value="Jenis Dokumen"
                                />
                                <select
                                    id="jenis_dokumen"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.jenis_dokumen}
                                    onChange={(e) =>
                                        setData(
                                            'jenis_dokumen',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="PR">PR</option>
                                    <option value="PO">PO</option>
                                    <option value="DO">DO</option>
                                    <option value="WO">WO</option>
                                </select>
                                <InputError
                                    message={errors.jenis_dokumen}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="no_dokumen"
                                    value="No. Dokumen"
                                />
                                <TextInput
                                    id="no_dokumen"
                                    className="mt-1 block w-full"
                                    value={data.no_dokumen}
                                    onChange={(e) =>
                                        setData(
                                            'no_dokumen',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    isFocused
                                />
                                <InputError
                                    message={errors.no_dokumen}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="kode_po"
                                    value="Kode PO (opsional)"
                                />
                                <TextInput
                                    id="kode_po"
                                    className="mt-1 block w-full"
                                    value={data.kode_po}
                                    onChange={(e) =>
                                        setData('kode_po', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.kode_po}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="nilai_po"
                                    value="Nilai PO"
                                />
                                <TextInput
                                    id="nilai_po"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.nilai_po}
                                    onChange={(e) =>
                                        setData('nilai_po', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.nilai_po}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4 grid grid-cols-2 gap-3">
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

                                <div>
                                    <InputLabel
                                        htmlFor="tanggal_selesai"
                                        value="Tanggal Selesai"
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
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="jumlah_tk_rencana"
                                    value="Jumlah TK Rencana"
                                />
                                <TextInput
                                    id="jumlah_tk_rencana"
                                    type="number"
                                    className="mt-1 block w-full"
                                    value={data.jumlah_tk_rencana}
                                    onChange={(e) =>
                                        setData(
                                            'jumlah_tk_rencana',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.jumlah_tk_rencana}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="keterangan"
                                    value="Keterangan (opsional)"
                                />
                                <textarea
                                    id="keterangan"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.keterangan}
                                    onChange={(e) =>
                                        setData(
                                            'keterangan',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.keterangan}
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

Save this to `resources/js/Pages/Jobs/Periods/Create.tsx`.

- [ ] **Step 3: Add the "Perbarui PR" button to the Jobs show page**

Now that `jobs.renew` exists, it's safe to reference it. In `resources/js/Pages/Jobs/Show.tsx`, change:

```tsx
                        {canManage && (
                            <div className="mt-4 flex gap-2">
                                <button
                                    type="button"
                                    onClick={toggleStatus}
                                    className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                >
                                    {job.status === 'aktif'
                                        ? 'Tandai Selesai'
                                        : 'Tandai Aktif'}
                                </button>
                            </div>
                        )}
```

to:

```tsx
                        {canManage && (
                            <div className="mt-4 flex gap-2">
                                {hasActivePeriod && (
                                    <Link
                                        href={route('jobs.renew', job.id)}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Perbarui PR
                                    </Link>
                                )}
                                <button
                                    type="button"
                                    onClick={toggleStatus}
                                    className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                >
                                    {job.status === 'aktif'
                                        ? 'Tandai Selesai'
                                        : 'Tandai Aktif'}
                                </button>
                            </div>
                        )}
```

Also add `Link` to the existing `@inertiajs/react` import at the top of the file — change:

```tsx
import { Head, router } from '@inertiajs/react';
```

to:

```tsx
import { Head, Link, router } from '@inertiajs/react';
```

- [ ] **Step 4: Rebuild frontend assets**

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20-alpine sh -c "npm run build"
```

Expected: 0 TypeScript errors.

- [ ] **Step 5: Run the full backend test suite**

Run: `docker compose exec app php artisan test`
Expected: all 94 tests still pass.

- [ ] **Step 6: Manual browser verification**

1. As an admin, open a Job's detail page that has an active period. Confirm "Perbarui PR" now appears — click it.
2. Confirm the 3-scenario picker renders. Click "(c) Assignment Saja" — confirm the info message appears without navigating away.
3. Click "(a) Lanjutan Job yang Sama" — fill the form with a new, unique No. Dokumen — submit. Confirm redirect back to the Job's detail page, the new period appears at the top of the history marked `aktif`, and the old period now shows `berakhir`.
4. From the picker again, click "(b) Job Baru Sama Sekali" — confirm it lands on the regular "Tambah Job" page (not a special renewal variant), and submitting creates a fully independent Job.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Jobs/Renew.tsx resources/js/Pages/Jobs/Periods resources/js/Pages/Jobs/Show.tsx
git commit -m "Add Perbarui PR frontend (scenario picker and scenario a form)"
```

---

### Task 6: Import backend (generalize error-report export, add Job importer)

**Files:**
- Modify: `app/Exports/ImportErrorsExport.php`
- Modify: `app/Http/Controllers/EmployeeImportController.php`
- Create: `app/Imports/JobsImport.php`
- Create: `app/Http/Controllers/JobImportController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Employees/EmployeeImportTest.php`
- Test: `tests/Feature/Jobs/JobImportTest.php`

**Interfaces:**
- Consumes: `Job`/`JobPeriod` models (Task 1).
- Produces: routes `jobs.import.create` (GET `/jobs/import`), `jobs.import.store` (POST `/jobs/import`), `jobs.import.errors` (GET `/jobs/import/errors`) — Task 7's frontend calls these. `JobsImport` public properties `created: int`, `errors: array<int, array<string, string>>` (each error row keyed `NO. DO/PR/WO`, `URAIAN PEKERJAAN`, `JUMLAH TK`, `MULAI TANGGAL`, `S/D TANGGAL`, `PO`, `NILAI PO`, `KETERANGAN`, `Alasan Gagal`). `ImportErrorsExport` now takes headings as a constructor argument (generalized from Task 1 of the Employee module) — both `EmployeeImportController` and `JobImportController` construct it with their own heading arrays.

**This task starts with a small, well-justified refactor** of `ImportErrorsExport` (currently hardcodes the Employee module's 5 column headings) before adding the Job importer — this is the second importer landing, which is exactly when that generalization was flagged as worth doing (see this plan's Architecture section).

- [ ] **Step 1: Generalize `ImportErrorsExport` and update its one existing caller**

Read the current `app/Exports/ImportErrorsExport.php` — it should look like:

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
    public function __construct(private readonly Collection $errors) {}

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

Replace it with:

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
     * @param  array<int, string>  $headings
     */
    public function __construct(
        private readonly Collection $errors,
        private readonly array $headings,
    ) {}

    public function collection(): Collection
    {
        return $this->errors;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->headings;
    }
}
```

Then, in `app/Http/Controllers/EmployeeImportController.php`, update its `downloadErrors()` method — change:

```php
        return Excel::download(
            new ImportErrorsExport(collect($errors)),
            'laporan-error-import-karyawan.xlsx',
        );
```

to:

```php
        return Excel::download(
            new ImportErrorsExport(collect($errors), ['NAMA', 'NIK', 'ALAMAT', 'NO REKENING', 'Alasan Gagal']),
            'laporan-error-import-karyawan.xlsx',
        );
```

- [ ] **Step 2: Run the existing Employee import tests to confirm the refactor didn't break anything**

Run: `docker compose exec app php artisan test --filter=EmployeeImportTest`
Expected: PASS, same count as before (11/11 — no new tests yet, this step just confirms the refactor is safe).

- [ ] **Step 3: Write the failing test for the Job importer**

```php
<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(
            ['NO. DO/PR/WO', 'URAIAN PEKERJAAN', 'JUMLAH TK', 'MULAI TANGGAL', 'S/D TANGGAL', 'PO', 'NILAI PO', 'KETERANGAN'],
            null,
            'A1',
        );

        foreach ($rows as $index => $row) {
            $sheet->fromArray($row, null, 'A'.($index + 2));
        }

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'jobs.xlsx', null, null, true);
    }

    public function test_viewer_cannot_access_import(): void
    {
        $this->actingAs($this->viewer())->get('/jobs/import')->assertForbidden();
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

        $this->assertDatabaseCount('jobs', 2);
        $this->assertDatabaseHas('jobs', ['nama_pekerjaan' => 'Helper Gudang']);

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
        $this->assertDatabaseCount('jobs', 0);
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
        $this->assertDatabaseCount('jobs', 1);
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
```

Save this to `tests/Feature/Jobs/JobImportTest.php`.

- [ ] **Step 4: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=JobImportTest`
Expected: FAIL — `/jobs/import` routes don't exist yet.

- [ ] **Step 5: Create the `JobsImport` class**

```php
<?php

namespace App\Imports;

use App\Enums\JobStatus;
use App\Enums\JobPeriodStatus;
use App\Models\Job;
use App\Models\JobPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

class JobsImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, string>> */
    public array $errors = [];

    public int $created = 0;

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $rawDocument = trim((string) ($row['no_do_pr_wo'] ?? ''));
            $uraian = trim((string) ($row['uraian_pekerjaan'] ?? ''));
            $jumlahTkRaw = trim((string) ($row['jumlah_tk'] ?? ''));
            $kodePo = trim((string) ($row['po'] ?? ''));
            $nilaiPoRaw = trim((string) ($row['nilai_po'] ?? ''));
            $keterangan = trim((string) ($row['keterangan'] ?? ''));

            $parsed = $this->parseDocument($rawDocument);

            if ($parsed === null) {
                $this->recordError($row, 'Format No. Dokumen tidak dikenali');

                continue;
            }

            if ($uraian === '') {
                $this->recordError($row, 'Uraian Pekerjaan wajib diisi');

                continue;
            }

            $jumlahTk = (int) preg_replace('/\D/', '', $jumlahTkRaw);

            if ($jumlahTk < 1) {
                $this->recordError($row, 'Jumlah TK wajib diisi dan berupa angka');

                continue;
            }

            $tanggalMulai = $this->parseDate($row['mulai_tanggal'] ?? null);

            if ($tanggalMulai === null) {
                $this->recordError($row, 'Mulai Tanggal wajib diisi dengan format tanggal yang valid');

                continue;
            }

            [$jenisDokumen, $noDokumen] = $parsed;

            if (JobPeriod::where('no_dokumen', $noDokumen)->exists()) {
                $this->recordError($row, 'No. Dokumen sudah terdaftar');

                continue;
            }

            $job = Job::create([
                'nama_pekerjaan' => $uraian,
                'status' => JobStatus::Aktif,
            ]);

            $job->periods()->create([
                'jenis_dokumen' => $jenisDokumen,
                'no_dokumen' => $noDokumen,
                'kode_po' => $kodePo !== '' ? $kodePo : null,
                'nilai_po' => $this->parseIndonesianNumber($nilaiPoRaw),
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $this->parseDate($row['s_d_tanggal'] ?? null),
                'jumlah_tk_rencana' => $jumlahTk,
                'keterangan' => $keterangan !== '' ? $keterangan : null,
                'status' => JobPeriodStatus::Aktif,
            ]);

            $this->created++;
        }
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parseDocument(string $value): ?array
    {
        foreach (['PR', 'DO', 'WO'] as $type) {
            if (preg_match('/'.$type.':\s*(\S+)/i', $value, $matches)) {
                return [$type, $matches[1]];
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Parse an Indonesian-formatted number ("6.812.604" or "1.234.567,89")
     * into a float. Indonesian notation uses "." as the thousands
     * separator and "," as the decimal separator — the reverse of
     * English notation.
     */
    private function parseIndonesianNumber(string $value): float
    {
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
            'NO. DO/PR/WO' => (string) ($row['no_do_pr_wo'] ?? ''),
            'URAIAN PEKERJAAN' => (string) ($row['uraian_pekerjaan'] ?? ''),
            'JUMLAH TK' => (string) ($row['jumlah_tk'] ?? ''),
            'MULAI TANGGAL' => (string) ($row['mulai_tanggal'] ?? ''),
            'S/D TANGGAL' => (string) ($row['s_d_tanggal'] ?? ''),
            'PO' => (string) ($row['po'] ?? ''),
            'NILAI PO' => (string) ($row['nilai_po'] ?? ''),
            'KETERANGAN' => (string) ($row['keterangan'] ?? ''),
            'Alasan Gagal' => $reason,
        ];
    }
}
```

Note on scope: date parsing handles native Excel date cells and plain ISO-ish strings (`Carbon::parse`) — it deliberately does NOT parse Indonesian month names like "07 Mei 2025" (Carbon's default locale won't recognize "Mei"). A row with an unparseable date format simply errors out with a clear reason, which is consistent with this module's "malformed rows go to the error report" design — full Indonesian date-format parsing is out of scope for this plan (YAGNI; add it later if real import files need it).

- [ ] **Step 6: Create the `JobImportController`**

```php
<?php

namespace App\Http\Controllers;

use App\Exports\ImportErrorsExport;
use App\Imports\JobsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JobImportController extends Controller
{
    private const HEADINGS = [
        'NO. DO/PR/WO',
        'URAIAN PEKERJAAN',
        'JUMLAH TK',
        'MULAI TANGGAL',
        'S/D TANGGAL',
        'PO',
        'NILAI PO',
        'KETERANGAN',
        'Alasan Gagal',
    ];

    public function create(): Response
    {
        return Inertia::render('Jobs/Import', [
            'result' => session('job_import_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new JobsImport;
        Excel::import($import, $request->file('file'));

        session(['job_import_errors' => $import->errors]);

        return redirect()->route('jobs.import.create')->with('job_import_result', [
            'created' => $import->created,
            'errorCount' => count($import->errors),
        ]);
    }

    public function downloadErrors(): BinaryFileResponse
    {
        $errors = session('job_import_errors', []);

        abort_if($errors === [], 404);

        session()->forget('job_import_errors');

        return Excel::download(
            new ImportErrorsExport(collect($errors), self::HEADINGS),
            'laporan-error-import-job.xlsx',
        );
    }
}
```

- [ ] **Step 7: Register the routes**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\JobImportController;
```

Add these routes inside the FIRST `role:admin|staff_input` sub-group under the `jobs` prefix (the one already containing `create`/`store`, registered before the `/{job}` wildcard — these are literal segments too, so they must stay before it):

```php
    Route::middleware('role:admin|staff_input')->group(function () {
        Route::get('/create', [JobController::class, 'create'])->name('create');
        Route::post('/', [JobController::class, 'store'])->name('store');
        Route::get('/import', [JobImportController::class, 'create'])->name('import.create');
        Route::post('/import', [JobImportController::class, 'store'])->name('import.store');
        Route::get('/import/errors', [JobImportController::class, 'downloadErrors'])->name('import.errors');
    });
```

- [ ] **Step 8: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=JobImportTest`
Expected: PASS (6/6).

- [ ] **Step 9: Run the full test suite**

Run: `docker compose exec app php artisan test`
Expected: all tests pass (94 + 6 = 100, since Step 2 confirmed the refactor added no new/lost tests).

- [ ] **Step 10: Format and commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Exports/ImportErrorsExport.php app/Http/Controllers/EmployeeImportController.php app/Imports/JobsImport.php app/Http/Controllers/JobImportController.php routes/web.php tests/Feature/Jobs/JobImportTest.php
git commit -m "Add Job Excel import backend, generalize ImportErrorsExport headings"
```

---

### Task 7: Import frontend

**Files:**
- Create: `resources/js/Pages/Jobs/Import.tsx`
- Modify: `resources/js/Pages/Jobs/Index.tsx`

**Interfaces:**
- Consumes: `jobs.import.create`/`jobs.import.store`/`jobs.import.errors` routes (Task 6).
- Produces: nothing later tasks in this plan depend on.

- [ ] **Step 1: Create the Import page**

```tsx
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
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
        post(route('jobs.import.store'), {
            forceFormData: true,
            onSuccess: () => reset('file'),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Import Data Job & Periode PR
                </h2>
            }
        >
            <Head title="Import Data Job & Periode PR" />

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
                                    <dt>Gagal</dt>
                                    <dd>{result.errorCount}</dd>
                                </div>
                            </dl>

                            {result.errorCount > 0 && (
                                <a
                                    href={route('jobs.import.errors')}
                                    className="mt-4 inline-block rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                >
                                    Download Laporan Error
                                </a>
                            )}
                        </div>
                    )}

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <p className="mb-4 text-sm text-slate-600">
                            Upload file Excel (.xlsx/.xls) dengan kolom NO.
                            DO/PR/WO, URAIAN PEKERJAAN, JUMLAH TK, MULAI
                            TANGGAL, S/D TANGGAL, PO, NILAI PO,
                            KETERANGAN. Setiap baris akan dibuat sebagai
                            Job baru.
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

Save this to `resources/js/Pages/Jobs/Import.tsx`.

- [ ] **Step 2: Add the "Import" button to the Jobs index page**

In `resources/js/Pages/Jobs/Index.tsx`, change:

```tsx
                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    <Link
                                        href={route('jobs.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Job
                                    </Link>
                                </div>
                            )}
```

to:

```tsx
                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    <Link
                                        href={route('jobs.import.create')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Import
                                    </Link>
                                    <Link
                                        href={route('jobs.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Job
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
Expected: all 100 tests still pass.

- [ ] **Step 5: Manual browser verification**

1. As an admin, on the Jobs list, confirm an "Import" button appears next to "Tambah Job" — click it.
2. Prepare a small `.xlsx` with the 8 required headers and 2 rows (one valid, one with an unrecognizable document pattern). Upload it.
3. Confirm the summary shows correct counts and a "Download Laporan Error" link for the failed row.
4. Download the error report — confirm it has the failed row and a reason column.
5. Go back to the Jobs list — confirm the valid row's Job appears with 1 period.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Jobs/Import.tsx resources/js/Pages/Jobs/Index.tsx
git commit -m "Add Job import frontend page and index Import button"
```

---

### Task 8: Export backend and Dashboard wiring

**Files:**
- Create: `app/Exports/JobsExport.php`
- Create: `app/Http/Controllers/JobExportController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/js/Pages/Dashboard.tsx`
- Modify: `resources/js/Pages/Jobs/Index.tsx`
- Test: `tests/Feature/Jobs/JobExportTest.php`
- Test: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: `Job`/`JobPeriod` models (Task 1).
- Produces: route `jobs.export` (GET `/jobs/export`). `DashboardController::index()` now also passes `jobCount: int` (count of active Jobs).

- [ ] **Step 1: Write the failing export test**

```php
<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_export(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get('/jobs/export')->assertForbidden();
    }

    public function test_admin_can_export(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $job = Job::factory()->create();
        JobPeriod::factory()->create(['job_id' => $job->id]);

        $this->actingAs($admin)
            ->get('/jobs/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
```

Save this to `tests/Feature/Jobs/JobExportTest.php`.

- [ ] **Step 2: Extend the failing dashboard test**

Read the current `tests/Feature/DashboardTest.php` first (it already has `test_dashboard_shows_user_counts_per_role` and `test_dashboard_shows_employee_count` from earlier plans). Add a new test method to that same file:

```php
    public function test_dashboard_shows_active_job_count(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        \App\Models\Job::factory()->count(2)->create();
        \App\Models\Job::factory()->selesai()->create();

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('jobCount', 2)
        );
    }
```

Note: `jobCount` counts only `aktif` jobs, not all jobs — matches the Dashboard's intent of showing current workload, not historical totals.

- [ ] **Step 3: Run both tests to verify they fail**

Run: `docker compose exec app php artisan test --filter=JobExportTest`
Expected: FAIL — `/jobs/export` doesn't exist.

Run: `docker compose exec app php artisan test --filter=DashboardTest`
Expected: FAIL — `jobCount` prop doesn't exist.

- [ ] **Step 4: Create the export class**

```php
<?php

namespace App\Exports;

use App\Models\JobPeriod;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class JobsExport implements FromCollection, WithHeadings
{
    /**
     * @return Collection<int, array<int, string|int|float|null>>
     */
    public function collection(): Collection
    {
        return JobPeriod::query()
            ->with('job')
            ->orderBy('job_id')
            ->get()
            ->map(fn (JobPeriod $period) => [
                $period->job->nama_pekerjaan,
                $period->jenis_dokumen->value,
                $period->no_dokumen,
                $period->kode_po,
                (float) $period->nilai_po,
                $period->tanggal_mulai->format('Y-m-d'),
                $period->tanggal_selesai?->format('Y-m-d'),
                $period->jumlah_tk_rencana,
                $period->status->value,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Nama Pekerjaan',
            'Jenis Dokumen',
            'No. Dokumen',
            'Kode PO',
            'Nilai PO',
            'Tanggal Mulai',
            'Tanggal Selesai',
            'Jumlah TK Rencana',
            'Status',
        ];
    }
}
```

- [ ] **Step 5: Create the export controller**

```php
<?php

namespace App\Http\Controllers;

use App\Exports\JobsExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JobExportController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return Excel::download(new JobsExport, 'data-job-periode-pr.xlsx');
    }
}
```

- [ ] **Step 6: Register the export route**

In `routes/web.php`, add the import:

```php
use App\Http\Controllers\JobExportController;
```

Add this route inside the FIRST `role:admin|staff_input` sub-group under the `jobs` prefix (alongside `create`, `import.*` — all literal segments before `/{job}`):

```php
        Route::get('/export', [JobExportController::class, 'download'])->name('export');
```

- [ ] **Step 7: Add the "Export" button to the Jobs index page**

In `resources/js/Pages/Jobs/Index.tsx`, change:

```tsx
                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    <Link
                                        href={route('jobs.import.create')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Import
                                    </Link>
                                    <Link
                                        href={route('jobs.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Job
                                    </Link>
                                </div>
                            )}
```

to:

```tsx
                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    <Link
                                        href={route('jobs.import.create')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Import
                                    </Link>
                                    <a
                                        href={route('jobs.export')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Export
                                    </a>
                                    <Link
                                        href={route('jobs.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Job
                                    </Link>
                                </div>
                            )}
```

- [ ] **Step 8: Wire the Dashboard's job count**

In `app/Http/Controllers/DashboardController.php`, add the import `use App\Enums\JobStatus;` and `use App\Models\Job;`, and add `'jobCount' => Job::where('status', JobStatus::Aktif)->count(),` to the array passed to `Inertia::render('Dashboard', [...])`, alongside the existing `'employeeCount'` and `'roleCounts'`.

- [ ] **Step 9: Update the Dashboard's "Job & PR" bento card**

In `resources/js/Pages/Dashboard.tsx`, add `jobCount` to the destructured props and its `PageProps` type (alongside the existing `roleCounts`/`employeeCount`), then replace the "Job & PR" `BentoCard` — currently:

```tsx
                        <BentoCard
                            title="Job & PR"
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
                        <BentoCard title="Job & PR" accent="white">
                            <div className="text-2xl font-bold text-pln-navy">
                                {jobCount}
                            </div>
                        </BentoCard>
```

(Drop the `badge="Segera Hadir"` prop entirely — this card now shows real data.)

- [ ] **Step 10: Run tests to verify they pass**

Run: `docker compose exec app php artisan test --filter=JobExportTest`
Expected: PASS (2/2).

Run: `docker compose exec app php artisan test --filter=DashboardTest`
Expected: PASS (3/3 — the two pre-existing tests plus the new job-count test).

- [ ] **Step 11: Rebuild frontend and run the full suite**

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20-alpine sh -c "npm run build"
docker compose exec app php artisan test
```

Expected: 0 TypeScript errors; all 100 + 2 (JobExportTest) + 1 (new DashboardTest method) = 103 tests pass.

- [ ] **Step 12: Manual browser verification**

1. As an admin, on the Jobs list, confirm the "Export" button appears — click it and confirm an `.xlsx` downloads with all Job Periods (joined to their Job's name).
2. Go to the Dashboard — confirm the "Job & PR" bento card now shows the real active-job count (no longer "Segera Hadir").
3. Log in as a `viewer` — confirm the Jobs list has no Export button, and navigating directly to `/jobs/export` returns 403.

- [ ] **Step 13: Format and commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Exports/JobsExport.php app/Http/Controllers/JobExportController.php routes/web.php app/Http/Controllers/DashboardController.php resources/js/Pages/Dashboard.tsx resources/js/Pages/Jobs/Index.tsx tests/Feature/Jobs/JobExportTest.php tests/Feature/DashboardTest.php
git commit -m "Add Job export and wire active job count into Dashboard"
```

---

## Definition of Done

- `docker compose exec app php artisan test` passes with 103 tests, 0 failures.
- `npm run build` completes with 0 TypeScript errors.
- Any authenticated user can view the Job list and a Job's detail page (history of periods, newest first).
- `admin`/`staff_input` can create a Job (with its first period), run all 3 "Perbarui PR" scenarios correctly (a: links + closes old period; b: fully independent new Job; c: informational message only), toggle Job status, import, and export; `viewer` gets 403 on all write routes.
- `no_dokumen` is verified unique across the whole `job_periods` table, not just within one Job.
- Excel import creates one independent Job+JobPeriod per valid row (never tries to match an existing Job), correctly parses the combined `NO. DO/PR/WO` column with PR>DO>WO priority, and produces a downloadable error report for unparseable or duplicate rows — all without a new database table.
- The Dashboard's "Job & PR" bento card shows a real count instead of "Segera Hadir".
- Nothing in this plan touches Assignment/Attendance domain code — that's the next sub-project.
