# Employee NIK/No. Rekening Encryption Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Encrypt `nik` and `no_rekening` at rest in the `employees` table, while preserving exact-match search, the unique-NIK constraint, and every existing consumer of NIK lookups — closing the PRD §9 NFR gap.

**Architecture:** Laravel's built-in `encrypted` Eloquent cast on `nik`/`no_rekening` (non-deterministic AES-256, uses the existing `APP_KEY`). A new `nik_hash` column (HMAC-SHA256, deterministic, its own `HASH_KEY` secret) becomes the target of every unique-constraint check and exact-match query that used to run directly against the (now-ciphertext) `nik` column — this is the standard "blind index" pattern for making encrypted data searchable without weakening the encryption. A one-time idempotent Artisan command migrates any pre-existing plaintext data.

**Tech Stack:** Laravel 13 (PHP 8.4), PostgreSQL 16 — no new packages; uses `Illuminate\Support\Facades\Crypt` and PHP's native `hash_hmac()`.

**Spec:** [docs/superpowers/specs/2026-09-15-employee-data-encryption-design.md](../specs/2026-09-15-employee-data-encryption-design.md)

## Global Constraints

- No new composer packages.
- `nik` and `no_rekening` columns widen from `string(16)`/`string(50)` to `text` (ciphertext is much longer than the plaintext).
- The DB `unique()` constraint on `nik` is dropped; a new `nik_hash` column (`string(64)`, nullable, unique) replaces it as the target of uniqueness and exact-match lookups.
- `Employee::hashNik(string $nik): string` is the ONE place that computes the HMAC — every query that needs to match/search NIK calls this, never re-implements the hash inline.
- The HMAC key comes from `config('app.hash_key')`, backed by a new `.env` variable `HASH_KEY` — a secret separate from `APP_KEY`, never derived from it.
- `nik_hash` is recomputed automatically by an Eloquent `saving` model event whenever `nik` is dirty — no call site is ever responsible for setting `nik_hash` manually, and it is NOT in the model's `#[Fillable(...)]` list.
- `no_rekening` gets ONLY the `encrypted` cast — no hash column, since nothing in the codebase does an exact-match/unique query against it.
- Masking (`App\Support\Masks::partial()`) call sites are NOT touched — the `encrypted` cast decrypts transparently on property access, so existing masking code keeps working unchanged.
- Deploy order (documented, not automated): (1) run the migration, (2) run `php artisan employees:encrypt-sensitive-data`, (3) deploy the code that activates the `encrypted` casts and the `nik_hash`-based queries. Reversing steps 2 and 3 causes every `Employee` read to throw `DecryptException`.
- Run `vendor/bin/pint --dirty --format agent` after any PHP change and `php artisan test --compact` before every commit.
- Every commit ends with the trailer `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`.

---

### Task 1: Migration, `Employee` model changes, and config/env wiring

**Files:**
- Create: `database/migrations/2026_09_15_000001_add_nik_hash_to_employees_table.php`
- Modify: `app/Models/Employee.php`
- Modify: `config/app.php`
- Modify: `.env`
- Modify: `.env.example`
- Test: `tests/Feature/Employees/EmployeeEncryptionTest.php`

**Interfaces:**
- Consumes: nothing (foundation task).
- Produces: `App\Models\Employee::hashNik(string $nik): string` (static method — every later task's query rewiring calls this). `Employee` casts `nik`/`no_rekening` as `'encrypted'`. A `nik_hash` column (`string(64)`, nullable, unique) automatically kept in sync with `nik` via a `saving` model event. `config('app.hash_key')` resolves to a real secret.

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
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['nik']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->text('nik')->change();
            $table->text('no_rekening')->change();
            $table->string('nik_hash', 64)->nullable()->unique()->after('nik');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Note: this rollback only restores the original column types/constraint
     * shape. It does NOT decrypt any data that was encrypted after this
     * migration ran — rolling back after real ciphertext exists in these
     * columns will leave that ciphertext sitting in a shorter/plain column,
     * which is a data-loss risk to be aware of, not something this
     * migration can safely prevent.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('nik_hash');
            $table->string('nik', 16)->change();
            $table->string('no_rekening', 50)->change();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('nik');
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate` (use `docker compose exec -T app php artisan migrate` if the host can't reach the DB directly — `DB_HOST=postgres` only resolves inside Docker)
Expected: migration applies cleanly, `employees` table now has a `nik_hash` column and `nik`/`no_rekening` are `text`.

- [ ] **Step 3: Add the `HASH_KEY` config**

In `config/app.php`, find the `'key' => env('APP_KEY'),` line (inside the array returned by the config file) and add a new entry right after the `'previous_keys' => [...]` block:

```php
    'hash_key' => env('HASH_KEY'),
```

- [ ] **Step 4: Add `HASH_KEY` to `.env` and `.env.example`**

In `.env`, add this line right after the `APP_KEY=...` line:

```
HASH_KEY=87aa56f85318ba8d3f0019a1eb0ca2dcc10cf5defb436de5b7ae62d9d8887445
```

In `.env.example`, add this line right after the `APP_KEY=` line (empty placeholder, matching how `APP_KEY=` is left empty there):

```
HASH_KEY=
```

- [ ] **Step 5: Write the failing test**

```php
<?php

namespace Tests\Feature\Employees;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_nik_is_stored_encrypted_and_reads_back_as_plaintext(): void
    {
        $employee = Employee::factory()->create(['nik' => '3513126804000099']);

        $raw = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertNotSame('3513126804000099', $raw->nik);
        $this->assertSame('3513126804000099', Crypt::decryptString($raw->nik));
        $this->assertSame('3513126804000099', $employee->fresh()->nik);
    }

    public function test_no_rekening_is_stored_encrypted_and_reads_back_as_plaintext(): void
    {
        $employee = Employee::factory()->create(['no_rekening' => '1923973699']);

        $raw = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertNotSame('1923973699', $raw->no_rekening);
        $this->assertSame('1923973699', Crypt::decryptString($raw->no_rekening));
        $this->assertSame('1923973699', $employee->fresh()->no_rekening);
    }

    public function test_nik_hash_is_computed_automatically_on_create(): void
    {
        $employee = Employee::factory()->create(['nik' => '3513126804000099']);

        $raw = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertSame(Employee::hashNik('3513126804000099'), $raw->nik_hash);
    }

    public function test_nik_hash_is_recomputed_when_nik_changes_on_update(): void
    {
        $employee = Employee::factory()->create(['nik' => '3513126804000099']);

        $employee->update(['nik' => '1111111111111111']);

        $raw = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertSame(Employee::hashNik('1111111111111111'), $raw->nik_hash);
        $this->assertNotSame(Employee::hashNik('3513126804000099'), $raw->nik_hash);
    }

    public function test_hash_nik_is_deterministic(): void
    {
        $this->assertSame(
            Employee::hashNik('3513126804000099'),
            Employee::hashNik('3513126804000099'),
        );
    }
}
```

- [ ] **Step 6: Run the test to verify it fails**

Run: `php artisan test --filter=EmployeeEncryptionTest`
Expected: FAIL — `nik_hash` doesn't exist as a queryable concept yet, `Employee::hashNik()` doesn't exist, casts aren't applied.

- [ ] **Step 7: Update the `Employee` model**

```php
<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'nik', 'alamat', 'no_rekening', 'nama_bank', 'status'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Employee $employee) {
            if ($employee->nik !== null && $employee->isDirty('nik')) {
                $employee->nik_hash = self::hashNik($employee->nik);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'nik' => 'encrypted',
            'no_rekening' => 'encrypted',
        ];
    }

    /**
     * Deterministic HMAC-SHA256 "blind index" for an employee's NIK.
     * This is the ONLY place that computes this hash — every exact-match
     * query against an encrypted `nik` must go through this method rather
     * than re-implementing the hash inline.
     */
    public static function hashNik(string $nik): string
    {
        return hash_hmac('sha256', $nik, (string) config('app.hash_key'));
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
```

- [ ] **Step 8: Run the test to verify it passes**

Run: `php artisan test --filter=EmployeeEncryptionTest`
Expected: PASS (5 tests)

- [ ] **Step 9: Format, run the full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Expected: some pre-existing tests in `EmployeeCrudTest`/`EmployeeImportTest` will now FAIL — this is expected and is fixed in Tasks 3 and 4, NOT in this task. Confirm the failures are limited to tests that do `assertDatabaseHas('employees', ['nik' => '<plaintext>', ...])` — anything else failing is a real regression to investigate. Do not attempt to fix those tests here.

```bash
git add database/migrations/2026_09_15_000001_add_nik_hash_to_employees_table.php app/Models/Employee.php config/app.php .env .env.example tests/Feature/Employees/EmployeeEncryptionTest.php
git commit -m "$(cat <<'EOF'
Encrypt Employee nik/no_rekening at rest, add nik_hash blind index

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: One-time backfill command for pre-existing plaintext data

**Files:**
- Create: `app/Console/Commands/EncryptEmployeeSensitiveData.php`
- Test: `tests/Feature/Console/EncryptEmployeeSensitiveDataTest.php`

**Interfaces:**
- Consumes: `App\Models\Employee::hashNik(string $nik): string` (Task 1), the `nik_hash` column (Task 1).
- Produces: Artisan command `employees:encrypt-sensitive-data` — no other task depends on this one's internals, only on it existing and being runnable.

- [ ] **Step 1: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class EncryptEmployeeSensitiveData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:encrypt-sensitive-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt any employees.nik/no_rekening values still stored as plaintext, and backfill nik_hash. Safe to run more than once.';

    public function handle(): int
    {
        $encrypted = 0;
        $skipped = 0;

        DB::table('employees')
            ->select(['id', 'nik', 'no_rekening'])
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$encrypted, &$skipped) {
                foreach ($rows as $row) {
                    $nikPlain = $this->plaintextIfNotYetEncrypted($row->nik);
                    $rekeningPlain = $this->plaintextIfNotYetEncrypted($row->no_rekening);

                    if ($nikPlain === null && $rekeningPlain === null) {
                        $skipped++;

                        continue;
                    }

                    $updates = [];

                    if ($nikPlain !== null) {
                        $updates['nik'] = Crypt::encryptString($nikPlain);
                        $updates['nik_hash'] = Employee::hashNik($nikPlain);
                    }

                    if ($rekeningPlain !== null) {
                        $updates['no_rekening'] = Crypt::encryptString($rekeningPlain);
                    }

                    DB::table('employees')->where('id', $row->id)->update($updates);
                    $encrypted++;
                }
            });

        $this->info("Encrypted {$encrypted} row(s), skipped {$skipped} row(s) already encrypted.");

        return self::SUCCESS;
    }

    /**
     * Returns the plaintext value if `$value` is NOT yet a valid
     * Laravel-encrypted payload (i.e. it still needs encrypting), or null
     * if it decrypts successfully (already encrypted — nothing to do).
     */
    private function plaintextIfNotYetEncrypted(string $value): ?string
    {
        try {
            Crypt::decryptString($value);

            return null;
        } catch (DecryptException) {
            return $value;
        }
    }
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

namespace Tests\Feature\Console;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EncryptEmployeeSensitiveDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Insert a row directly via the query builder (bypassing Eloquent's
     * `encrypted` cast) to simulate legacy plaintext data that predates
     * this feature.
     */
    private function insertPlaintextEmployee(string $nik, string $noRekening): int
    {
        return DB::table('employees')->insertGetId([
            'nama' => 'Legacy Employee',
            'nik' => $nik,
            'alamat' => 'Jl. Lama No. 1',
            'no_rekening' => $noRekening,
            'nama_bank' => 'BCA',
            'status' => 'aktif',
            'nik_hash' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_encrypts_plaintext_rows_and_backfills_nik_hash(): void
    {
        $id = $this->insertPlaintextEmployee('3513126804000099', '1923973699');

        $this->artisan('employees:encrypt-sensitive-data')->assertExitCode(0);

        $row = DB::table('employees')->where('id', $id)->first();

        $this->assertSame('3513126804000099', Crypt::decryptString($row->nik));
        $this->assertSame('1923973699', Crypt::decryptString($row->no_rekening));
        $this->assertSame(Employee::hashNik('3513126804000099'), $row->nik_hash);
    }

    public function test_it_is_idempotent_and_does_not_re_encrypt_already_encrypted_rows(): void
    {
        $id = $this->insertPlaintextEmployee('3513126804000099', '1923973699');

        $this->artisan('employees:encrypt-sensitive-data')->assertExitCode(0);

        $afterFirstRun = DB::table('employees')->where('id', $id)->first();

        $this->artisan('employees:encrypt-sensitive-data')->assertExitCode(0);

        $afterSecondRun = DB::table('employees')->where('id', $id)->first();

        $this->assertSame($afterFirstRun->nik, $afterSecondRun->nik);
        $this->assertSame($afterFirstRun->no_rekening, $afterSecondRun->no_rekening);
        $this->assertSame($afterFirstRun->nik_hash, $afterSecondRun->nik_hash);
    }

    public function test_it_leaves_already_encrypted_rows_untouched(): void
    {
        $employee = Employee::factory()->create(['nik' => '2222222222222222', 'no_rekening' => '5555555555']);
        $before = DB::table('employees')->where('id', $employee->id)->first();

        $this->artisan('employees:encrypt-sensitive-data')->assertExitCode(0);

        $after = DB::table('employees')->where('id', $employee->id)->first();

        $this->assertSame($before->nik, $after->nik);
        $this->assertSame($before->no_rekening, $after->no_rekening);
        $this->assertSame($before->nik_hash, $after->nik_hash);
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --filter=EncryptEmployeeSensitiveDataTest`
Expected: FAIL — the command doesn't exist yet.

- [ ] **Step 4: Run the test to verify it passes**

(The command was already written in Step 1 above — this step just confirms it.)

Run: `php artisan test --filter=EncryptEmployeeSensitiveDataTest`
Expected: PASS (3 tests)

- [ ] **Step 5: Format, run the full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Expected: the same pre-existing `EmployeeCrudTest`/`EmployeeImportTest` failures from Task 1 remain (still fixed in Tasks 3/4) — no NEW failures should appear from this task's changes.

```bash
git add app/Console/Commands/EncryptEmployeeSensitiveData.php tests/Feature/Console/EncryptEmployeeSensitiveDataTest.php
git commit -m "$(cat <<'EOF'
Add idempotent backfill command for legacy plaintext Employee data

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: `StoreEmployeeRequest`/`UpdateEmployeeRequest` unique-NIK validation via `nik_hash`

**Files:**
- Modify: `app/Http/Requests/StoreEmployeeRequest.php`
- Modify: `app/Http/Requests/UpdateEmployeeRequest.php`
- Modify: `tests/Feature/Employees/EmployeeCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Employee::hashNik(string $nik): string` (Task 1).
- Produces: nothing further consumed by later tasks.

- [ ] **Step 1: Update `StoreEmployeeRequest`**

```php
<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\Validator;
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
            'nik' => ['required', 'string', 'regex:/^\d{16}$/'],
            'alamat' => ['required', 'string'],
            'no_rekening' => ['required', 'string', 'regex:/^\d+$/'],
            'nama_bank' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $nik = $this->input('nik');

            if (! $nik) {
                return;
            }

            if (Employee::where('nik_hash', Employee::hashNik($nik))->exists()) {
                $validator->errors()->add('nik', 'NIK sudah terdaftar.');
            }
        });
    }
}
```

- [ ] **Step 2: Update `UpdateEmployeeRequest`**

```php
<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

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
            'nik' => ['required', 'string', 'regex:/^\d{16}$/'],
            'alamat' => ['required', 'string'],
            'no_rekening' => ['required', 'string', 'regex:/^\d+$/'],
            'nama_bank' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $nik = $this->input('nik');

            if (! $nik) {
                return;
            }

            $exists = Employee::where('nik_hash', Employee::hashNik($nik))
                ->where('id', '!=', $this->route('employee')->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('nik', 'NIK sudah terdaftar.');
            }
        });
    }
}
```

- [ ] **Step 3: Fix the two broken assertions in `EmployeeCrudTest.php`**

`tests/Feature/Employees/EmployeeCrudTest.php` already imports `use App\Models\Employee;` at the top of the file — no import changes needed.

Find this block in `test_admin_can_create_an_employee_with_valid_data`:

```php
        $response->assertSessionHas('success', 'Karyawan berhasil ditambahkan.');
        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'nik' => '3513126804000099',
            'nama' => 'Budi Santoso',
            'status' => 'aktif',
        ]);
    }
```

Replace with:

```php
        $response->assertSessionHas('success', 'Karyawan berhasil ditambahkan.');
        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'nama' => 'Budi Santoso',
            'status' => 'aktif',
        ]);
        $employee = Employee::where('nama', 'Budi Santoso')->firstOrFail();
        $this->assertSame('3513126804000099', $employee->nik);
    }
```

Find the identical block in `test_staff_input_can_create_an_employee` and apply the same replacement.

- [ ] **Step 4: Run the affected tests**

Run: `php artisan test --filter=EmployeeCrudTest`
Expected: PASS (all tests in this file, including the two just fixed)

- [ ] **Step 5: Format, run the full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Expected: only the `EmployeeImportTest` failure from Task 1 remains (fixed in Task 4) — no other failures.

```bash
git add app/Http/Requests/StoreEmployeeRequest.php app/Http/Requests/UpdateEmployeeRequest.php tests/Feature/Employees/EmployeeCrudTest.php
git commit -m "$(cat <<'EOF'
Validate unique NIK via nik_hash instead of the encrypted nik column

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: `EmployeesImport`/`AssignmentsImport` duplicate-check via `nik_hash`

**Files:**
- Modify: `app/Imports/EmployeesImport.php`
- Modify: `app/Imports/AssignmentsImport.php`
- Modify: `tests/Feature/Employees/EmployeeImportTest.php`

**Interfaces:**
- Consumes: `App\Models\Employee::hashNik(string $nik): string` (Task 1).
- Produces: nothing further consumed by later tasks.

- [ ] **Step 1: Update `EmployeesImport`'s duplicate lookup**

In `app/Imports/EmployeesImport.php`, find:

```php
            $existing = Employee::where('nik', $nik)->first();
```

Replace with:

```php
            $existing = Employee::where('nik_hash', Employee::hashNik($nik))->first();
```

- [ ] **Step 2: Update `AssignmentsImport`'s employee lookup**

In `app/Imports/AssignmentsImport.php`, find:

```php
            $employee = Employee::where('nik', $nik)->first();
```

Replace with:

```php
            $employee = Employee::where('nik_hash', Employee::hashNik($nik))->first();
```

- [ ] **Step 3: Fix the broken assertion in `EmployeeImportTest.php`**

In `tests/Feature/Employees/EmployeeImportTest.php`, find:

```php
        $this->assertDatabaseHas('employees', ['nik' => '3513126804000001', 'nama' => 'Budi Santoso']);
```

Replace with:

```php
        $employee = Employee::where('nama', 'Budi Santoso')->firstOrFail();
        $this->assertSame('3513126804000001', $employee->nik);
```

`tests/Feature/Employees/EmployeeImportTest.php` already imports `use App\Models\Employee;` at the top of the file — no import changes needed.

- [ ] **Step 4: Run the affected tests**

Run: `php artisan test --filter=EmployeeImportTest`
Run: `php artisan test --filter=AssignmentImportTest`
Expected: both PASS

- [ ] **Step 5: Format, run the full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Expected: fully green — no known failures should remain from Tasks 1-4.

```bash
git add app/Imports/EmployeesImport.php app/Imports/AssignmentsImport.php tests/Feature/Employees/EmployeeImportTest.php
git commit -m "$(cat <<'EOF'
Match Employee by nik_hash in Excel imports instead of encrypted nik

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Exact-match NIK search on the Employee index page

**Files:**
- Modify: `app/Http/Controllers/EmployeeController.php`
- Modify: `tests/Feature/Employees/EmployeeCrudTest.php`

**Interfaces:**
- Consumes: `App\Models\Employee::hashNik(string $nik): string` (Task 1).
- Produces: nothing further consumed by later tasks (this is the final task).

- [ ] **Step 1: Write the failing tests**

Add these two tests to `tests/Feature/Employees/EmployeeCrudTest.php` (alongside the existing `test_search_filters_by_name_or_nik`):

```php
    public function test_search_finds_employee_by_full_exact_nik(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nama' => 'Budi Santoso', 'nik' => '1111111111111111']);
        Employee::factory()->create(['nama' => 'Siti Aminah', 'nik' => '2222222222222222']);

        $response = $this->actingAs($admin)->get('/employees?search=2222222222222222', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonCount(1, 'props.employees.data');
        $response->assertJsonPath('props.employees.data.0.nama', 'Siti Aminah');
    }

    public function test_search_with_partial_nik_finds_nothing_via_nik(): void
    {
        $admin = $this->admin();
        Employee::factory()->create(['nama' => 'Budi Santoso', 'nik' => '1111111111111111']);

        $response = $this->actingAs($admin)->get('/employees?search=11111111', $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonCount(0, 'props.employees.data');
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=EmployeeCrudTest`
Expected: FAIL on `test_search_finds_employee_by_full_exact_nik` — searching a 16-digit NIK doesn't match yet, because the controller still does a `LIKE` directly against the ciphertext `nik` column, which never matches a plaintext search term.

- [ ] **Step 3: Update the search query in `EmployeeController::index()`**

Find:

```php
        $employees = Employee::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            })
```

Replace with:

```php
        $employees = Employee::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%");

                    if (preg_match('/^\d{16}$/', $search)) {
                        $query->orWhere('nik_hash', Employee::hashNik($search));
                    }
                });
            })
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=EmployeeCrudTest`
Expected: PASS (all tests in this file, including the two new ones)

- [ ] **Step 5: Format, run the full suite, and commit**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
```

Expected: fully green.

```bash
git add app/Http/Controllers/EmployeeController.php tests/Feature/Employees/EmployeeCrudTest.php
git commit -m "$(cat <<'EOF'
Make Employee NIK search exact-match via nik_hash

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```
