# Auth & Role Dasar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add role-based access control (admin / staff_input / viewer) to WorkTrack — seeded roles, an admin-bootstrap Artisan command, disabled public registration, an Admin-only User Management UI, and role info shared with every Inertia page — so later Fase 1 modules (Employee, Job, Assignment, Attendance) have a role system to gate against.

**Architecture:** `spatie/laravel-permission` (already installed) provides the `roles`/`model_has_roles` tables and the `HasRoles` trait. Three fixed roles are seeded once; there is no role/permission CRUD UI — roles are assigned to users through a small Admin-only controller. Public self-registration is removed; the only way to create a user is an Admin using the new UI, or the Artisan command for the very first admin.

**Tech Stack:** Laravel 13, `spatie/laravel-permission` ^8.3, Inertia.js + React + TypeScript (Breeze react-ts stack), PHPUnit (class-based tests, not Pest).

**Spec:** [docs/superpowers/specs/2026-09-10-auth-roles-design.md](../specs/2026-09-10-auth-roles-design.md) (parent spec: [docs/superpowers/specs/2026-09-09-worktrack-mvp-design.md](../specs/2026-09-09-worktrack-mvp-design.md) §8.5, K2)

## Global Constraints

- Only 3 fixed roles exist: `admin`, `staff_input`, `viewer` — no dynamic role/permission CRUD (spec D1-D3, §1).
- Public registration (`/register`) must not exist after this plan (spec §3.3).
- New users created by Admin default to role `viewer` unless a different role is explicitly chosen in the form (spec §3.5).
- The first Admin account is bootstrapped via `php artisan worktrack:create-admin`, never a hardcoded seeder credential (spec D3).
- All commands run via Docker: `docker compose exec app <command>` for PHP/Composer/artisan, `docker compose exec app php artisan test` for the suite. No host PHP/Node required.
- Frontend role checks (hiding nav links, etc.) are cosmetic only — real enforcement is server-side route middleware/form requests (spec §3.6).

---

### Task 1: Role seeding infrastructure

**Files:**
- Modify: `app/Models/User.php`
- Create: `database/seeders/RoleSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/RoleSeederTest.php`

**Interfaces:**
- Produces: `User` model has `HasRoles` trait (methods `assignRole()`, `hasRole()`, `getRoleNames()`, `syncRoles()` become available) — Tasks 2, 4, 5 call these directly. The `role` route middleware alias (`Spatie\Permission\Middleware\RoleMiddleware`) is registered — Task 5's routes use `middleware('role:admin')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_three_fixed_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertTrue(Role::where('name', 'admin')->exists());
        $this->assertTrue(Role::where('name', 'staff_input')->exists());
        $this->assertTrue(Role::where('name', 'viewer')->exists());
        $this->assertSame(3, Role::count());
    }

    public function test_role_seeder_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(3, Role::count());
    }
}
```

Save this to `tests/Feature/RoleSeederTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=RoleSeederTest`
Expected: FAIL — `Class "Database\Seeders\RoleSeeder" not found`.

- [ ] **Step 3: Create the RoleSeeder**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's fixed roles.
     */
    public function run(): void
    {
        foreach (['admin', 'staff_input', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=RoleSeederTest`
Expected: PASS (2/2).

- [ ] **Step 5: Add the `HasRoles` trait to the User model**

In `app/Models/User.php`, add the import and add `HasRoles` to the existing `use` trait statement:

```php
use Spatie\Permission\Traits\HasRoles;
```

Change:
```php
use HasFactory, Notifiable;
```
to:
```php
use HasFactory, Notifiable, HasRoles;
```

- [ ] **Step 6: Register the `role` middleware alias**

In `bootstrap/app.php`, inside the existing `->withMiddleware(function (Middleware $middleware): void { ... })` closure, add after the `$middleware->web(append: [...]);` call:

```php
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
```

- [ ] **Step 7: Wire the seeder into DatabaseSeeder**

In `database/seeders/DatabaseSeeder.php`, add the call at the top of `run()`:

```php
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
```

- [ ] **Step 8: Run the full test suite to confirm nothing broke**

Run: `docker compose exec app php artisan test`
Expected: all tests pass (the pre-existing 25 plus the 2 new ones = 27).

- [ ] **Step 9: Commit**

```bash
git add app/Models/User.php database/seeders/RoleSeeder.php database/seeders/DatabaseSeeder.php bootstrap/app.php tests/Feature/RoleSeederTest.php
git commit -m "Add role seeding infrastructure (admin/staff_input/viewer)"
```

---

### Task 2: `worktrack:create-admin` Artisan command

**Files:**
- Create: `app/Console/Commands/CreateAdminCommand.php`
- Test: `tests/Feature/Console/CreateAdminCommandTest.php`

**Interfaces:**
- Consumes: `User::create()`, `$user->assignRole('admin')` (from Task 1's `HasRoles` trait).
- Produces: nothing later tasks depend on programmatically — this is an operator-facing bootstrap tool.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_user_with_the_admin_role(): void
    {
        $this->seed(RoleSeeder::class);

        $this->artisan('worktrack:create-admin')
            ->expectsQuestion('Nama admin', 'Budi Admin')
            ->expectsQuestion('Email admin', 'budi@worktrack.test')
            ->expectsQuestion('Password admin', 'password123')
            ->assertExitCode(0);

        $user = User::where('email', 'budi@worktrack.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_it_rejects_a_duplicate_email(): void
    {
        $this->seed(RoleSeeder::class);
        User::factory()->create(['email' => 'existing@worktrack.test']);

        $this->artisan('worktrack:create-admin')
            ->expectsQuestion('Nama admin', 'Budi Admin')
            ->expectsQuestion('Email admin', 'existing@worktrack.test')
            ->expectsQuestion('Password admin', 'password123')
            ->assertExitCode(1);
    }
}
```

Save this to `tests/Feature/Console/CreateAdminCommandTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=CreateAdminCommandTest`
Expected: FAIL — `worktrack:create-admin` command not found.

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'worktrack:create-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Buat akun admin WorkTrack pertama (bootstrap)';

    public function handle(): int
    {
        $name = $this->ask('Nama admin');
        $email = $this->ask('Email admin');
        $password = $this->secret('Password admin');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->assignRole('admin');

        $this->info("Admin '{$user->email}' berhasil dibuat.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=CreateAdminCommandTest`
Expected: PASS (2/2).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/CreateAdminCommand.php tests/Feature/Console/CreateAdminCommandTest.php
git commit -m "Add worktrack:create-admin bootstrap command"
```

---

### Task 3: Disable public registration

**Files:**
- Modify: `routes/auth.php`
- Delete: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Delete: `resources/js/Pages/Auth/Register.tsx`
- Delete: `tests/Feature/Auth/RegistrationTest.php`
- Modify: `resources/js/Pages/Welcome.tsx`
- Test: `tests/Feature/Auth/PublicRegistrationDisabledTest.php`

**Interfaces:**
- Consumes: nothing from prior tasks.
- Produces: `/register` no longer resolves — Task 6 must not link to it.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicRegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_registration_submission_is_not_available(): void
    {
        $this->post('/register', [
            'name' => 'Someone',
            'email' => 'someone@worktrack.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }
}
```

Save this to `tests/Feature/Auth/PublicRegistrationDisabledTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=PublicRegistrationDisabledTest`
Expected: FAIL — both requests currently return 200/302, not 404.

- [ ] **Step 3: Remove the register routes**

In `routes/auth.php`, delete the `use App\Http\Controllers\Auth\RegisteredUserController;` import line and delete these two routes from inside the `Route::middleware('guest')->group(...)` block:

```php
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);
```

- [ ] **Step 4: Delete the now-unused controller, page, and stale test**

```bash
rm app/Http/Controllers/Auth/RegisteredUserController.php
rm resources/js/Pages/Auth/Register.tsx
rm tests/Feature/Auth/RegistrationTest.php
```

- [ ] **Step 5: Fix `Welcome.tsx` so it no longer calls `route('register')` unconditionally**

In `resources/js/Pages/Welcome.tsx`, change the function signature from:

```tsx
export default function Welcome({
    auth,
    laravelVersion,
    phpVersion,
}: PageProps<{ laravelVersion: string; phpVersion: string }>) {
```

to:

```tsx
export default function Welcome({
    auth,
    canRegister,
    laravelVersion,
    phpVersion,
}: PageProps<{
    canRegister: boolean;
    laravelVersion: string;
    phpVersion: string;
}>) {
```

Then change the guest nav block from:

```tsx
                                    <>
                                        <Link
                                            href={route('login')}
                                            className="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                                        >
                                            Log in
                                        </Link>
                                        <Link
                                            href={route('register')}
                                            className="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                                        >
                                            Register
                                        </Link>
                                    </>
```

to:

```tsx
                                    <>
                                        <Link
                                            href={route('login')}
                                            className="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                                        >
                                            Log in
                                        </Link>
                                        {canRegister && (
                                            <Link
                                                href={route('register')}
                                                className="rounded-md px-3 py-2 text-black ring-1 ring-transparent transition hover:text-black/70 focus:outline-none focus-visible:ring-[#FF2D20] dark:text-white dark:hover:text-white/80 dark:focus-visible:ring-white"
                                            >
                                                Register
                                            </Link>
                                        )}
                                    </>
```

`routes/web.php`'s `/` route already passes `'canRegister' => Route::has('register')`, which is now `false` since Step 3 removed the route — no change needed there.

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=PublicRegistrationDisabledTest`
Expected: PASS (2/2).

- [ ] **Step 7: Run the full test suite**

Run: `docker compose exec app php artisan test`
Expected: all tests pass (27 from Task 1 minus the 2 deleted `RegistrationTest` tests, plus the 2 new ones here, plus Task 2's 2 = 29).

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "Disable public registration; admin-only user creation going forward"
```

---

### Task 4: Share authenticated user's roles with every Inertia page

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/types/index.d.ts`
- Test: `tests/Feature/HandleInertiaRequestsRolesTest.php`

**Interfaces:**
- Consumes: `$user->getRoleNames()` (from Task 1's `HasRoles` trait).
- Produces: every Inertia page's `usePage().props.auth.user.roles` is a `string[]` — Task 6's nav link and any future module's frontend role checks read this exact shape.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HandleInertiaRequestsRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_roles_are_shared_with_inertia(): void
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('viewer');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.roles', ['viewer'])
        );
    }
}
```

Save this to `tests/Feature/HandleInertiaRequestsRolesTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=HandleInertiaRequestsRolesTest`
Expected: FAIL — `auth.user.roles` prop does not exist (current share only sends the raw user model, no `roles` key).

- [ ] **Step 3: Update `HandleInertiaRequests::share()`**

Replace the `share()` method body in `app/Http/Middleware/HandleInertiaRequests.php`:

```php
    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->toArray(),
                    'roles' => $user->getRoleNames(),
                ] : null,
            ],
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=HandleInertiaRequestsRolesTest`
Expected: PASS (1/1).

- [ ] **Step 5: Update the shared TypeScript `User` type**

In `resources/js/types/index.d.ts`, change:

```ts
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}
```

to:

```ts
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    roles: string[];
}
```

- [ ] **Step 6: Run the full test suite**

Run: `docker compose exec app php artisan test`
Expected: all tests pass (29 + 1 = 30).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/types/index.d.ts tests/Feature/HandleInertiaRequestsRolesTest.php
git commit -m "Share authenticated user's roles with every Inertia page"
```

---

### Task 5: Admin-only User Management backend

**Files:**
- Create: `app/Http/Requests/StoreUserRequest.php`
- Create: `app/Http/Requests/UpdateUserRequest.php`
- Create: `app/Http/Controllers/Admin/UserController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: `role:admin` middleware alias (Task 1), `HasRoles` trait methods (Task 1).
- Produces: routes `users.index` (GET `/users`), `users.create` (GET `/users/create`), `users.store` (POST `/users`), `users.edit` (GET `/users/{user}/edit`), `users.update` (PUT `/users/{user}`) — Task 6's frontend pages call these by name via Ziggy's `route()`. `Inertia::render('Users/Index', ['users' => [...]])` where each user is `{id, name, email, role}` (`role` is a single string or `null`, not an array — spec's 3 fixed roles are mutually exclusive per user in this UI). `Inertia::render('Users/Create')` (no props). `Inertia::render('Users/Edit', ['user' => {id, name, email, role}])`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_non_admin_cannot_access_user_management(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get('/users')->assertForbidden();
    }

    public function test_admin_can_create_user_with_default_viewer_role(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Staff Baru',
            'email' => 'staff.baru@worktrack.test',
            'password' => 'password123',
            'role' => 'viewer',
        ]);

        $response->assertRedirect('/users');

        $newUser = User::where('email', 'staff.baru@worktrack.test')->first();
        $this->assertNotNull($newUser);
        $this->assertTrue($newUser->hasRole('viewer'));
        $this->assertFalse($newUser->hasRole('staff_input'));
    }

    public function test_admin_can_create_user_with_explicit_staff_input_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Staff Input',
            'email' => 'staff.input@worktrack.test',
            'password' => 'password123',
            'role' => 'staff_input',
        ]);

        $newUser = User::where('email', 'staff.input@worktrack.test')->first();
        $this->assertTrue($newUser->hasRole('staff_input'));
        $this->assertFalse($newUser->hasRole('viewer'));
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole('viewer');

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'role' => 'staff_input',
        ])->assertRedirect('/users');

        $user->refresh();
        $this->assertTrue($user->hasRole('staff_input'));
        $this->assertFalse($user->hasRole('viewer'));
    }
}
```

Save this to `tests/Feature/Admin/UserManagementTest.php`.

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=UserManagementTest`
Expected: FAIL — `/users` routes don't exist yet (404s where 403/302 expected).

- [ ] **Step 3: Create the form requests**

`app/Http/Requests/StoreUserRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Authorization is enforced by the `role:admin` route middleware —
     * this request only validates the payload shape.
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', 'string', 'in:admin,staff_input,viewer'],
        ];
    }
}
```

`app/Http/Requests/UpdateUserRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Authorization is enforced by the `role:admin` route middleware —
     * this request only validates the payload shape.
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
            'role' => ['required', 'string', 'in:admin,staff_input,viewer'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        $user->assignRole($request->validated('role'));

        return redirect()->route('users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
            ],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->syncRoles([$request->validated('role')]);

        return redirect()->route('users.index');
    }
}
```

- [ ] **Step 5: Register the routes**

In `routes/web.php`, add the import near the top:

```php
use App\Http\Controllers\Admin\UserController;
```

Then add this group before `require __DIR__.'/auth.php';`:

```php
Route::middleware(['auth', 'role:admin'])->prefix('users')->name('users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/create', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
});
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=UserManagementTest`
Expected: PASS (4/4).

- [ ] **Step 7: Run the full test suite**

Run: `docker compose exec app php artisan test`
Expected: all tests pass (30 + 4 = 34).

- [ ] **Step 8: Commit**

```bash
git add app/Http/Requests/StoreUserRequest.php app/Http/Requests/UpdateUserRequest.php app/Http/Controllers/Admin/UserController.php routes/web.php tests/Feature/Admin/UserManagementTest.php
git commit -m "Add admin-only User Management backend (index/create/store/edit/update)"
```

---

### Task 6: User Management frontend pages and nav link

**Files:**
- Create: `resources/js/Pages/Users/Index.tsx`
- Create: `resources/js/Pages/Users/Create.tsx`
- Create: `resources/js/Pages/Users/Edit.tsx`
- Modify: `resources/js/Layouts/AuthenticatedLayout.tsx`

**Interfaces:**
- Consumes: `users.index`/`users.create`/`users.store`/`users.edit`/`users.update` routes and their exact prop shapes (Task 5); `auth.user.roles: string[]` (Task 4).
- Produces: nothing further tasks in this plan depend on — this is the plan's final task.

There is no automated frontend test in this plan (no JS test runner is configured yet); verification is a manual browser check per the Definition of Done below, matching how Task 4 of the project-setup plan verified Breeze's login page.

- [ ] **Step 1: Create the Users index page**

```tsx
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string | null;
}

export default function Index({ users }: PageProps<{ users: UserRow[] }>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Manajemen User
                </h2>
            }
        >
            <Head title="Manajemen User" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <div className="mb-4 flex justify-end">
                            <Link
                                href={route('users.create')}
                                className="rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700"
                            >
                                Tambah User
                            </Link>
                        </div>

                        <table className="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Nama
                                    </th>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Email
                                    </th>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Role
                                    </th>
                                    <th className="px-3 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {users.map((user) => (
                                    <tr key={user.id}>
                                        <td className="px-3 py-2 text-sm text-gray-900">
                                            {user.name}
                                        </td>
                                        <td className="px-3 py-2 text-sm text-gray-500">
                                            {user.email}
                                        </td>
                                        <td className="px-3 py-2 text-sm text-gray-500">
                                            {user.role ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right text-sm">
                                            <Link
                                                href={route(
                                                    'users.edit',
                                                    user.id,
                                                )}
                                                className="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Edit
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

Save this to `resources/js/Pages/Users/Index.tsx`.

- [ ] **Step 2: Create the Users create page**

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
        name: '',
        email: '',
        password: '',
        role: 'viewer',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('users.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Tambah User
                </h2>
            }
        >
            <Head title="Tambah User" />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel htmlFor="name" value="Nama" />
                                <TextInput
                                    id="name"
                                    className="mt-1 block w-full"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                    isFocused
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel htmlFor="email" value="Email" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.email}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="password"
                                    value="Password"
                                />
                                <TextInput
                                    id="password"
                                    type="password"
                                    className="mt-1 block w-full"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.password}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel htmlFor="role" value="Role" />
                                <select
                                    id="role"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.role}
                                    onChange={(e) =>
                                        setData('role', e.target.value)
                                    }
                                >
                                    <option value="viewer">Viewer</option>
                                    <option value="staff_input">
                                        Staff Input
                                    </option>
                                    <option value="admin">Admin</option>
                                </select>
                                <InputError
                                    message={errors.role}
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

Save this to `resources/js/Pages/Users/Create.tsx`. Note the `role` field defaults to `'viewer'` in `useForm`'s initial state, matching spec D2/§3.5 — the dropdown is pre-selected to Viewer unless the Admin changes it.

- [ ] **Step 3: Create the Users edit page**

```tsx
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EditableUser {
    id: number;
    name: string;
    email: string;
    role: string | null;
}

export default function Edit({ user }: PageProps<{ user: EditableUser }>) {
    const { data, setData, put, processing, errors } = useForm({
        role: user.role ?? 'viewer',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('users.update', user.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit User: {user.name}
                </h2>
            }
        >
            <Head title={`Edit ${user.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <p className="mb-4 text-sm text-gray-500">
                            {user.email}
                        </p>

                        <form onSubmit={submit}>
                            <div>
                                <InputLabel htmlFor="role" value="Role" />
                                <select
                                    id="role"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.role}
                                    onChange={(e) =>
                                        setData('role', e.target.value)
                                    }
                                >
                                    <option value="viewer">Viewer</option>
                                    <option value="staff_input">
                                        Staff Input
                                    </option>
                                    <option value="admin">Admin</option>
                                </select>
                                <InputError
                                    message={errors.role}
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

Save this to `resources/js/Pages/Users/Edit.tsx`.

- [ ] **Step 4: Add an Admin-only "Users" nav link**

In `resources/js/Layouts/AuthenticatedLayout.tsx`, the desktop nav currently has:

```tsx
                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                <NavLink
                                    href={route('dashboard')}
                                    active={route().current('dashboard')}
                                >
                                    Dashboard
                                </NavLink>
                            </div>
```

Change it to:

```tsx
                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
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
                            </div>
```

And the mobile nav currently has:

```tsx
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
                        </ResponsiveNavLink>
```

Change it to:

```tsx
                        <ResponsiveNavLink
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                        >
                            Dashboard
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

`user` is already destructured at the top of this component (`const user = usePage().props.auth.user;`) — no new import needed. This relies on Task 4's `roles: string[]` addition to the shared `User` type.

- [ ] **Step 5: Rebuild frontend assets**

```bash
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html node:20-alpine sh -c "npm install --legacy-peer-deps && npm run build"
```

- [ ] **Step 6: Run the full backend test suite**

Run: `docker compose exec app php artisan test`
Expected: all 34 tests still pass (this task made no backend changes).

- [ ] **Step 7: Manual browser verification**

1. Run `docker compose exec app php artisan worktrack:create-admin` and create an admin account (any test email/password).
2. Open `http://localhost:8080/login` in a browser, log in as that admin.
3. Confirm a "Manajemen User" link appears in the nav; click it — the Index page lists the admin account itself with role `admin`.
4. Click "Tambah User", fill the form leaving Role at its default "Viewer", submit — confirm redirect back to the index page and the new user appears with role `viewer`.
5. Click "Edit" on that new user, change role to "Staff Input", submit — confirm the index page now shows `staff_input` for that user.
6. Log out, log in as the newly created (non-admin) user — confirm the "Manajemen User" nav link is absent and navigating directly to `/users` returns a 403 page.

- [ ] **Step 8: Commit**

```bash
git add resources/js/Pages/Users resources/js/Layouts/AuthenticatedLayout.tsx
git commit -m "Add User Management frontend pages and admin-only nav link"
```

---

## Definition of Done

- `docker compose exec app php artisan test` passes with 34 tests, 0 failures.
- `/register` returns 404; the only way to create a user is the Admin UI or `worktrack:create-admin`.
- An Admin can create a user (default role `viewer`, or an explicitly chosen role) and change an existing user's role, all through the browser.
- A non-admin cannot reach `/users*` (403) and does not see the nav link.
- Every Inertia page's `auth.user.roles` reflects the logged-in user's actual roles.
- Nothing in this plan touches Employee/Job/JobPeriod/Assignment/Attendance domain code — that's the next sub-project.
