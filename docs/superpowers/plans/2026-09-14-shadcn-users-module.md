# shadcn/ui Migration — Sub-Project 5 (Users) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate the Users management module's 3 pages (Index, Create, Edit) to shadcn/ui — the final sub-project of the shadcn/ui migration. Convert Create/Edit into Dialogs, use shadcn `Select` for the role dropdown (settling the native-`<select>`-vs-shadcn-`Select` question left open by sub-project 4's final review), and add toast notifications.

**Architecture:** Same pattern as every prior sub-project: routes/controllers keep `Inertia::render()`ing the same components; only the rendering wraps in Dialog with `open` always `true`, closing via `router.visit(route('users.index'))`. This module has no navigation hierarchy (unlike Job/Assignment) — every Dialog closes straight to `users.index`. This is the smallest sub-project so far: no search/filter/pagination on Index, no Import/Export, no multi-scenario flows.

**Tech Stack:** Laravel 13, Inertia.js + React 18 + TypeScript, shadcn/ui primitives from sub-project 1 (the `DialogContent` overflow fix from sub-project 4's final review already applies here automatically, since it lives in the shared primitive).

**Spec:** [docs/superpowers/specs/2026-09-12-shadcn-ui-migration-design.md](../specs/2026-09-12-shadcn-ui-migration-design.md) §1 (sub-project 5), §3 (Dialog pattern).

## Global Constraints

- No route/controller signature changes except the specific `->with('success', ...)` additions this plan calls for.
- Every `DialogContent` gets `aria-describedby={undefined}` — established convention from prior sub-projects.
- The role dropdown in both `Create.tsx` and `Edit.tsx` uses shadcn `Select` (NOT a native `<select>`) — this sub-project settles the convention left open in sub-project 4's final review, since Users is a fresh module with no existing native-select precedent to preserve.
- Do not delete or modify old Breeze-scaffold components (`PrimaryButton`, `TextInput`, `InputLabel`, `InputError`) — this is the LAST sub-project touching pages, but the component files themselves are left as-is regardless (no other module needs them deleted as part of this migration).
- Run `npx tsc --noEmit`, `php artisan test --compact`, and `vendor/bin/pint --dirty --format agent` (for PHP changes) before every commit.
- Every commit ends with the trailer `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>` — a process gap in an earlier sub-project's first few tasks mistakenly omitted this; do not repeat it.
- No test in `tests/Feature/Admin/UserManagementTest.php` asserts on page markup — all assert on redirects and DB/role state, none of which change in this plan except the new `assertSessionHas('success', ...)` additions this plan itself adds.

---

### Task 1: Migrate Users/Index.tsx to shadcn Primitives

**Files:**
- Modify: `resources/js/Pages/Users/Index.tsx`

**Interfaces:**
- Consumes: `@/Components/ui/button`, `@/Components/ui/table` (sub-project 1).
- Produces: nothing new for later tasks — `<Link>` targets (`users.create`, `users.edit`) unchanged.

- [ ] **Step 1: Replace the button and table**

```tsx
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, RoleName } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: RoleName | null;
}

export default function Index({ users }: PageProps<{ users: UserRow[] }>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Manajemen User
                </h2>
            }
        >
            <Head title="Manajemen User" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <div className="mb-4 flex justify-end">
                            <Button asChild>
                                <Link href={route('users.create')}>
                                    Tambah User
                                </Link>
                            </Button>
                        </div>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell>
                                                {user.name}
                                            </TableCell>
                                            <TableCell>
                                                {user.email}
                                            </TableCell>
                                            <TableCell>
                                                {user.role ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Link
                                                    href={route(
                                                        'users.edit',
                                                        user.id,
                                                    )}
                                                    className="text-pln-blue hover:text-pln-blue-dark"
                                                >
                                                    Edit
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 2: Verify and commit**

```bash
npx tsc --noEmit
php artisan test --compact
git add resources/js/Pages/Users/Index.tsx
git commit -m "$(cat <<'EOF'
Migrate Users Index to shadcn Button/Table

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Migrate Users/Create.tsx to a Dialog with shadcn Select + Add Toast

**Files:**
- Modify: `resources/js/Pages/Users/Create.tsx`
- Modify: `app/Http/Controllers/Admin/UserController.php` (`store()` only)
- Modify: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/input`, `@/Components/ui/label`, `@/Components/ui/select`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page.

- [ ] **Step 1: Rewrite `Create.tsx` as a Dialog with shadcn `Select` for role**

```tsx
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
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

    const close = () => router.visit(route('users.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Manajemen User
                </h2>
            }
        >
            <Head title="Tambah User" />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent aria-describedby={undefined}>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>Tambah User</DialogTitle>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="name">Nama</Label>
                                <Input
                                    id="name"
                                    className="mt-1"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                    autoFocus
                                />
                                {errors.name && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    required
                                />
                                {errors.email && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    className="mt-1"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.password && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="role">Role</Label>
                                <Select
                                    value={data.role}
                                    onValueChange={(value) =>
                                        setData('role', value)
                                    }
                                >
                                    <SelectTrigger id="role" className="mt-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="viewer">
                                            Viewer
                                        </SelectItem>
                                        <SelectItem value="staff_input">
                                            Staff Input
                                        </SelectItem>
                                        <SelectItem value="admin">
                                            Admin
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.role && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.role}
                                    </p>
                                )}
                            </div>
                        </div>

                        <DialogFooter className="mt-6">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={close}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 2: Add a flash message to `UserController::store()`**

Change:

```php
        return redirect()->route('users.index');
```

to:

```php
        return redirect()->route('users.index')->with('success', 'User berhasil ditambahkan.');
```

- [ ] **Step 3: Update the tests**

In `tests/Feature/Admin/UserManagementTest.php`:

In `test_admin_can_create_user_with_default_viewer_role`, add `->assertSessionHas('success', 'User berhasil ditambahkan.')` right before the existing `$response->assertRedirect('/users');` call (change it to a chained call on `$response`, e.g. `$response->assertSessionHas('success', 'User berhasil ditambahkan.')->assertRedirect('/users');` or two separate statements — either is fine as long as both assertions run).

In `test_admin_can_create_user_with_explicit_staff_input_role`, this test currently does NOT capture the response (`$this->actingAs($admin)->post('/users', [...]);` as a bare statement with no assertion on the HTTP response at all). Capture it into a `$response` variable and add `$response->assertSessionHas('success', 'User berhasil ditambahkan.');` after it, without disturbing the existing `$newUser`-based assertions that follow.

- [ ] **Step 4: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Users/Create.tsx app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "$(cat <<'EOF'
Migrate User Create to a Dialog with shadcn Select and add a success toast

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Migrate Users/Edit.tsx to a Dialog with shadcn Select + Add Toast

**Files:**
- Modify: `resources/js/Pages/Users/Edit.tsx`
- Modify: `app/Http/Controllers/Admin/UserController.php` (`update()` only)
- Modify: `tests/Feature/Admin/UserManagementTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/label`, `@/Components/ui/select`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page, and this is the last task of the entire shadcn/ui migration project.

- [ ] **Step 1: Rewrite `Edit.tsx` as a Dialog with shadcn `Select` for role**

```tsx
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, RoleName } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EditableUser {
    id: number;
    name: string;
    email: string;
    role: RoleName | null;
}

export default function Edit({ user }: PageProps<{ user: EditableUser }>) {
    const { data, setData, put, processing, errors } = useForm({
        role: user.role ?? 'viewer',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('users.update', user.id));
    };

    const close = () => router.visit(route('users.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Manajemen User
                </h2>
            }
        >
            <Head title={`Edit ${user.name}`} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent aria-describedby={undefined}>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                Edit User: {user.name}
                            </DialogTitle>
                        </DialogHeader>

                        <p className="mt-1 text-sm text-gray-500">
                            {user.email}
                        </p>

                        <div className="mt-4">
                            <Label htmlFor="role">Role</Label>
                            <Select
                                value={data.role}
                                onValueChange={(value) =>
                                    setData('role', value as RoleName)
                                }
                            >
                                <SelectTrigger id="role" className="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="viewer">
                                        Viewer
                                    </SelectItem>
                                    <SelectItem value="staff_input">
                                        Staff Input
                                    </SelectItem>
                                    <SelectItem value="admin">
                                        Admin
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.role && (
                                <p className="mt-2 text-sm text-destructive">
                                    {errors.role}
                                </p>
                            )}
                        </div>

                        <DialogFooter className="mt-6">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={close}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 2: Add a flash message to `UserController::update()`**

Change:

```php
        return redirect()->route('users.index');
```

to:

```php
        return redirect()->route('users.index')->with('success', 'Role user berhasil diperbarui.');
```

- [ ] **Step 3: Update the tests**

In `tests/Feature/Admin/UserManagementTest.php`:

In `test_admin_can_change_a_users_role`, the request/assertion is a single chained statement:

```php
        $this->actingAs($admin)->put("/users/{$user->id}", [
            'role' => 'staff_input',
        ])->assertRedirect('/users');
```

Change it to add the new assertion into the same chain, before `assertRedirect`:

```php
        $this->actingAs($admin)->put("/users/{$user->id}", [
            'role' => 'staff_input',
        ])->assertSessionHas('success', 'Role user berhasil diperbarui.')->assertRedirect('/users');
```

In `test_admin_can_change_another_admins_role_when_a_second_admin_exists` (the test whose body ends with `])->assertRedirect('/users');` per this file's existing pattern), apply the same chained-assertion insertion: add `->assertSessionHas('success', 'Role user berhasil diperbarui.')` immediately before that test's own `->assertRedirect('/users')` call.

Do NOT add this assertion to `test_sole_admin_cannot_demote_themselves` — that test expects a validation failure (`assertRedirect()` with no args, i.e. redirect back with errors), not a success flash.

- [ ] **Step 4: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Users/Edit.tsx app/Http/Controllers/Admin/UserController.php tests/Feature/Admin/UserManagementTest.php
git commit -m "$(cat <<'EOF'
Migrate User Edit to a Dialog with shadcn Select and add a success toast

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```
