# shadcn/ui Migration — Sub-Project 3 (Employee) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate all 5 Employee pages (Index, Create, Edit, Show, Import) to shadcn/ui components, converting Create/Edit/Show into Dialogs and Import into a Sheet per the established pattern, and add toast notifications to every write action.

**Architecture:** Routes and controllers stay unchanged — every route still `Inertia::render()`s to the same page component it always has. Only the rendering changes: `Create`/`Edit` become a `Dialog` with `open` always `true` (the visited route itself IS the "open" state); `Show` becomes a wider `Dialog` (more content — the assignment-history table); `Import` becomes a `Sheet`. Closing any of them calls `router.visit(route('employees.index'))`. `Index.tsx` itself stays a full page — only its internal controls (search input, status select, buttons, table) get swapped for shadcn primitives; its `<Link>`s to `employees.create`/`employees.edit`/`employees.show`/`employees.import.create` are untouched, since navigating to those routes is exactly what opens each Dialog/Sheet.

**Tech Stack:** Laravel 13, Inertia.js + React 18 + TypeScript, shadcn/ui primitives generated in sub-project 1 (`resources/js/Components/ui/*`).

**Spec:** [docs/superpowers/specs/2026-09-12-shadcn-ui-migration-design.md](../specs/2026-09-12-shadcn-ui-migration-design.md) §1 (sub-project 3), §3 (Dialog/Sheet pattern reference).

## Global Constraints

- No route or controller signature changes — every `Inertia::render()` call keeps rendering the same component to the same props shape it already does, EXCEPT for the specific `->with('success', ...)` flash-message additions this plan calls for.
- Do not delete or modify the old Breeze-scaffold components (`PrimaryButton`, `TextInput`, `InputLabel`, `InputError`) — only stop importing them in the 5 files this plan touches. Job/Users pages still use them.
- Every Dialog/Sheet's `onOpenChange={(open) => { if (!open) { router.visit(route('employees.index')); } }}` — closing (Cancel/X/outside-click/Escape) always navigates back to the Employee list.
- `Pagination.tsx` (used by `Index.tsx`) is shared with the not-yet-migrated Job module — leave it untouched; it renders fine as-is inside the migrated page.
- Run `npx tsc --noEmit`, `php artisan test --compact`, and `vendor/bin/pint --dirty --format agent` (for PHP changes) before every commit.
- No test in `tests/Feature/Employees/*` asserts on page markup — all assert on redirects, DB state, session flash, and Inertia component/prop names, none of which change in this plan except the new `assertSessionHas('success', ...)` additions this plan itself adds.

---

### Task 1: Migrate Index.tsx to shadcn Primitives

**Files:**
- Modify: `resources/js/Pages/Employees/Index.tsx`

**Interfaces:**
- Consumes: `@/Components/ui/button`, `@/Components/ui/input`, `@/Components/ui/select`, `@/Components/ui/table` (all generated in sub-project 1).
- Produces: nothing new for later tasks — `Index.tsx`'s `<Link>` targets (`employees.create`, `employees.edit`, `employees.show`, `employees.import.create`) are unchanged, so Tasks 2-5 don't depend on anything from this task beyond those routes already existing.

- [ ] **Step 1: Replace the filter form and table**

```tsx
import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
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
                                <Input
                                    type="text"
                                    value={search}
                                    onChange={(e) =>
                                        setSearch(e.target.value)
                                    }
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Status
                                </label>
                                <Select
                                    value={status === '' ? 'semua' : status}
                                    onValueChange={(value) =>
                                        setStatus(
                                            value === 'semua' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger className="mt-1 w-[160px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="semua">
                                            Semua
                                        </SelectItem>
                                        <SelectItem value="aktif">
                                            Aktif
                                        </SelectItem>
                                        <SelectItem value="non_aktif">
                                            Non-aktif
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button type="submit">Terapkan</Button>

                            {canManage && (
                                <div className="ml-auto flex gap-2">
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={route(
                                                'employees.import.create',
                                            )}
                                        >
                                            Import
                                        </Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <a href={route('employees.export')}>
                                            Export
                                        </a>
                                    </Button>
                                    <Button asChild>
                                        <Link
                                            href={route('employees.create')}
                                        >
                                            Tambah Karyawan
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </form>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>NIK</TableHead>
                                        <TableHead>No. Rekening</TableHead>
                                        <TableHead>Status</TableHead>
                                        {canManage && <TableHead />}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {employees.data.map((employee) => (
                                        <TableRow key={employee.id}>
                                            <TableCell>
                                                <Link
                                                    href={route(
                                                        'employees.show',
                                                        employee.id,
                                                    )}
                                                    className="text-pln-blue hover:underline"
                                                >
                                                    {employee.nama}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {employee.nik}
                                            </TableCell>
                                            <TableCell>
                                                {employee.no_rekening}
                                            </TableCell>
                                            <TableCell>
                                                {employee.status === 'aktif'
                                                    ? 'Aktif'
                                                    : 'Non-aktif'}
                                            </TableCell>
                                            {canManage && (
                                                <TableCell className="text-right">
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
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination links={employees.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

Notes: the status `<Select>` needs a non-empty sentinel value (`"semua"`) because Radix's `Select.Item` cannot have an empty-string `value` — the component translates `"semua"` ↔ `""` at the boundary so `filters.status`/the query-string behavior is unchanged. `Edit`/toggle-status stay plain `<Link>`/`<button>` (not `Button` components) to keep their existing compact inline-text styling inside the table row, matching how Job's Index table actions look — this is a deliberate style choice, not an oversight.

- [ ] **Step 2: Verify and commit**

```bash
npx tsc --noEmit
php artisan test --compact
git add resources/js/Pages/Employees/Index.tsx
git commit -m "Migrate Employee Index to shadcn Button/Input/Select/Table"
```

---

### Task 2: Migrate Create.tsx to a Dialog + Add Toast

**Files:**
- Modify: `resources/js/Pages/Employees/Create.tsx`
- Modify: `app/Http/Controllers/EmployeeController.php` (`store()` only)
- Modify: `tests/Feature/Employees/EmployeeCrudTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/input`, `@/Components/ui/label`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page.

- [ ] **Step 1: Rewrite `Create.tsx` as a Dialog**

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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
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

    const close = () => router.visit(route('employees.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Karyawan
                </h2>
            }
        >
            <Head title="Tambah Karyawan" />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>Tambah Karyawan</DialogTitle>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="nama">Nama</Label>
                                <Input
                                    id="nama"
                                    className="mt-1"
                                    value={data.nama}
                                    onChange={(e) =>
                                        setData('nama', e.target.value)
                                    }
                                    required
                                    autoFocus
                                />
                                {errors.nama && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nama}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="nik">NIK</Label>
                                <Input
                                    id="nik"
                                    className="mt-1"
                                    value={data.nik}
                                    onChange={(e) =>
                                        setData('nik', e.target.value)
                                    }
                                    maxLength={16}
                                    required
                                />
                                {errors.nik && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nik}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="alamat">Alamat</Label>
                                <textarea
                                    id="alamat"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.alamat}
                                    onChange={(e) =>
                                        setData('alamat', e.target.value)
                                    }
                                    required
                                />
                                {errors.alamat && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.alamat}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="no_rekening">
                                    No. Rekening
                                </Label>
                                <Input
                                    id="no_rekening"
                                    className="mt-1"
                                    value={data.no_rekening}
                                    onChange={(e) =>
                                        setData(
                                            'no_rekening',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.no_rekening && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.no_rekening}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="nama_bank">
                                    Nama Bank (opsional)
                                </Label>
                                <Input
                                    id="nama_bank"
                                    className="mt-1"
                                    value={data.nama_bank}
                                    onChange={(e) =>
                                        setData('nama_bank', e.target.value)
                                    }
                                />
                                {errors.nama_bank && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nama_bank}
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

- [ ] **Step 2: Add a flash message to `EmployeeController::store()`**

Change:

```php
        return redirect()->route('employees.index');
```

to:

```php
        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil ditambahkan.');
```

- [ ] **Step 3: Update the tests**

In `tests/Feature/Employees/EmployeeCrudTest.php`, add `->assertSessionHas('success', 'Karyawan berhasil ditambahkan.')` to both `test_admin_can_create_an_employee_with_valid_data` and `test_staff_input_can_create_an_employee`, inserted right before their existing `assertRedirect(route('employees.index'))` call (same position/pattern as the Assignment/Job modules' equivalent test updates).

- [ ] **Step 4: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Employees/Create.tsx app/Http/Controllers/EmployeeController.php tests/Feature/Employees/EmployeeCrudTest.php
git commit -m "Migrate Employee Create to a Dialog and add a success toast"
```

---

### Task 3: Migrate Edit.tsx to a Dialog + Add Toasts

**Files:**
- Modify: `resources/js/Pages/Employees/Edit.tsx`
- Modify: `app/Http/Controllers/EmployeeController.php` (`update()` and `toggleStatus()`)
- Modify: `tests/Feature/Employees/EmployeeCrudTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/input`, `@/Components/ui/label`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page.

- [ ] **Step 1: Rewrite `Edit.tsx` as a Dialog**

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
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EmployeeDetail, PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
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

    const close = () => router.visit(route('employees.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Karyawan
                </h2>
            }
        >
            <Head title={`Edit ${employee.nama}`} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                Edit Karyawan: {employee.nama}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="nama">Nama</Label>
                                <Input
                                    id="nama"
                                    className="mt-1"
                                    value={data.nama}
                                    onChange={(e) =>
                                        setData('nama', e.target.value)
                                    }
                                    required
                                    autoFocus
                                />
                                {errors.nama && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nama}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="nik">NIK</Label>
                                <Input
                                    id="nik"
                                    className="mt-1"
                                    value={data.nik}
                                    onChange={(e) =>
                                        setData('nik', e.target.value)
                                    }
                                    maxLength={16}
                                    required
                                />
                                {errors.nik && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nik}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="alamat">Alamat</Label>
                                <textarea
                                    id="alamat"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.alamat}
                                    onChange={(e) =>
                                        setData('alamat', e.target.value)
                                    }
                                    required
                                />
                                {errors.alamat && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.alamat}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="no_rekening">
                                    No. Rekening
                                </Label>
                                <Input
                                    id="no_rekening"
                                    className="mt-1"
                                    value={data.no_rekening}
                                    onChange={(e) =>
                                        setData(
                                            'no_rekening',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.no_rekening && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.no_rekening}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="nama_bank">
                                    Nama Bank (opsional)
                                </Label>
                                <Input
                                    id="nama_bank"
                                    className="mt-1"
                                    value={data.nama_bank}
                                    onChange={(e) =>
                                        setData('nama_bank', e.target.value)
                                    }
                                />
                                {errors.nama_bank && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nama_bank}
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

- [ ] **Step 2: Add flash messages to `EmployeeController::update()` and `toggleStatus()`**

Change `update()`'s return from:

```php
        return redirect()->route('employees.index');
```

to:

```php
        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil diperbarui.');
```

Change `toggleStatus()`'s return from:

```php
        return redirect()->route('employees.index');
```

to:

```php
        return redirect()->route('employees.index')->with('success', 'Status karyawan berhasil diperbarui.');
```

- [ ] **Step 3: Update the tests**

In `tests/Feature/Employees/EmployeeCrudTest.php`:
- Add `->assertSessionHas('success', 'Karyawan berhasil diperbarui.')` to `test_admin_can_update_an_employee_and_keep_its_own_nik` and to `test_updating_an_employee_does_not_change_its_status`, before their `assertRedirect` calls.
- Add `->assertSessionHas('success', 'Status karyawan berhasil diperbarui.')` to `test_admin_can_toggle_employee_status` and `test_staff_input_can_toggle_employee_status`, before their `assertRedirect` calls.

- [ ] **Step 4: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Employees/Edit.tsx app/Http/Controllers/EmployeeController.php tests/Feature/Employees/EmployeeCrudTest.php
git commit -m "Migrate Employee Edit to a Dialog and add success toasts"
```

---

### Task 4: Migrate Show.tsx to a Wider Dialog

**Files:**
- Modify: `resources/js/Pages/Employees/Show.tsx`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/table` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page.

- [ ] **Step 1: Rewrite `Show.tsx` as a wider Dialog**

```tsx
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EmployeeAssignmentRow, EmployeeDetail, PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';

export default function Show({
    employee,
    assignments,
}: PageProps<{
    employee: EmployeeDetail;
    assignments: EmployeeAssignmentRow[];
}>) {
    const close = () => router.visit(route('employees.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Karyawan
                </h2>
            }
        >
            <Head title={employee.nama} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{employee.nama}</DialogTitle>
                    </DialogHeader>

                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-slate-500">NIK</dt>
                            <dd className="font-medium">{employee.nik}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">No. Rekening</dt>
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

                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-pln-navy">
                            Riwayat Penugasan
                        </h3>

                        <div className="max-h-80 overflow-y-auto overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Job</TableHead>
                                        <TableHead>No. Dokumen</TableHead>
                                        <TableHead>Periode</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell>
                                                {
                                                    assignment.job_nama_pekerjaan
                                                }
                                            </TableCell>
                                            <TableCell>
                                                {assignment.no_dokumen}
                                            </TableCell>
                                            <TableCell>
                                                {assignment.tanggal_mulai}
                                                {assignment.tanggal_selesai
                                                    ? ` s/d ${assignment.tanggal_selesai}`
                                                    : ''}
                                            </TableCell>
                                            <TableCell>
                                                {assignment.status}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 2: Verify and commit**

```bash
npx tsc --noEmit
php artisan test --compact
git add resources/js/Pages/Employees/Show.tsx
git commit -m "Migrate Employee Show to a wider Dialog"
```

---

### Task 5: Migrate Import.tsx to a Sheet + Add Toast

**Files:**
- Modify: `resources/js/Pages/Employees/Import.tsx`
- Modify: `app/Http/Controllers/EmployeeImportController.php` (`store()` only)
- Modify: `tests/Feature/Employees/EmployeeImportTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/sheet`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page, and this is the last task in this sub-project.

- [ ] **Step 1: Rewrite `Import.tsx` as a Sheet**

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

    const close = () => router.visit(route('employees.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Karyawan
                </h2>
            }
        >
            <Head title="Import Data Karyawan" />

            <Sheet
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <SheetContent className="overflow-y-auto sm:max-w-lg">
                    <SheetHeader>
                        <SheetTitle>Import Data Karyawan</SheetTitle>
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
                                        href={route(
                                            'employees.import.errors',
                                        )}
                                        className="mt-4 inline-block rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Download Laporan Error
                                    </a>
                                )}
                            </div>
                        )}

                        <div>
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

- [ ] **Step 2: Add a flash message to `EmployeeImportController::store()`**

Change:

```php
        return redirect()->route('employees.import.create')->with('employee_import_result', [
            'created' => $import->created,
            'skipped' => $import->skipped,
            'errorCount' => count($import->errors),
        ]);
```

to:

```php
        return redirect()->route('employees.import.create')
            ->with('employee_import_result', [
                'created' => $import->created,
                'skipped' => $import->skipped,
                'errorCount' => count($import->errors),
            ])
            ->with('success', "Import selesai: {$import->created} berhasil, {$import->skipped} dilewati, ".count($import->errors).' gagal.');
```

- [ ] **Step 3: Update the tests**

In `tests/Feature/Employees/EmployeeImportTest.php`, in `test_valid_rows_are_imported` (created=2, skipped=0, errorCount=0 for this test's fixture), add this assertion right after the existing `assertSessionHas('employee_import_result', ...)` call:

```php
$response->assertSessionHas('success', 'Import selesai: 2 berhasil, 0 dilewati, 0 gagal.');
```

- [ ] **Step 4: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Employees/Import.tsx app/Http/Controllers/EmployeeImportController.php tests/Feature/Employees/EmployeeImportTest.php
git commit -m "Migrate Employee Import to a Sheet and add a summary toast"
```
