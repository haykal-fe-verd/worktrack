# shadcn/ui Migration — Sub-Project 4 (Job & Assignment) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate all 10 Job/Assignment pages to shadcn/ui, converting Create/Show/Renew/Periods-Create into Dialogs (Job Show and JobPeriod Show wider, for their tables), Import into a Sheet, and all Assignment pages (Create/Edit/End) into Dialogs — folded into this sub-project because their navigation chain (Job → JobPeriod → Assignment) is directly connected (per the revision note in the parent spec). Add toast notifications to every write action, including the Assignment controllers which predate the toast infrastructure and never got one.

**Architecture:** Same pattern as sub-projects 1 and 3: routes/controllers keep `Inertia::render()`ing the same components; only the rendering wraps in Dialog/Sheet with `open` always `true`. The one architectural wrinkle here is the **closing target is not always the top-level index** — because this module has a real navigation hierarchy (Job list → a Job → one of its JobPeriods → an Assignment on that period), each Dialog/Sheet closes back to its logical parent, not always `jobs.index`:

- `Jobs/Create`, `Jobs/Show` close → `jobs.index`
- `Jobs/Renew`, `Jobs/Periods/Create` close → `jobs.show` (the Job that spawned them)
- `Jobs/Import` closes → `jobs.index`
- `JobPeriods/Show` closes → `jobs.show` (using `jobPeriod.job_id`, already in its props)
- `Assignments/Create` closes → `job-periods.show` (using the `jobPeriod.id` already in its props)
- `Assignments/Edit`, `Assignments/End` close → `job-periods.show` (using `job_period_id` — **not yet in their props**, added in this plan)

**Tech Stack:** Laravel 13, Inertia.js + React 18 + TypeScript, shadcn/ui primitives from sub-project 1.

**Spec:** [docs/superpowers/specs/2026-09-12-shadcn-ui-migration-design.md](../specs/2026-09-12-shadcn-ui-migration-design.md) §1 (sub-project 4, revised 13 Sept 2026 to include Assignment), §3 (Dialog/Sheet pattern, nested-dialog reference).

## Global Constraints

- No route signature changes at all in this plan — every route keeps its exact name, method, and middleware.
- No controller method signature changes except: the specific `->with('success'/'error', ...)` additions this plan calls for, and adding `'job_period_id' => $assignment->job_period_id` to `AssignmentController::edit()`'s and `endForm()`'s existing Inertia props array.
- Every `DialogContent`/`SheetContent` gets `aria-describedby={undefined}` (the sanctioned Radix escape hatch established in sub-project 3's final review) — titles in this module are self-explanatory, so no `DialogDescription`/`SheetDescription` text is added.
- Do not delete or modify old Breeze-scaffold components (`PrimaryButton`, `TextInput`, `InputLabel`, `InputError`) — Users module (sub-project 5) still uses them.
- `Pagination.tsx` (used by `Jobs/Index.tsx`) stays untouched — shared with the already-migrated Employee module.
- Run `npx tsc --noEmit`, `php artisan test --compact`, and `vendor/bin/pint --dirty --format agent` (for PHP changes) before every commit.
- No test in `tests/Feature/Jobs/*`, `tests/Feature/Assignments/*`, or `tests/Feature/JobPeriods/*` asserts on page markup — all assert on redirects, DB state, session flash, and Inertia component/prop names, none of which change in this plan except the new `assertSessionHas('success'/'error', ...)` additions this plan itself adds.

---

### Task 1: Migrate Jobs/Index.tsx to shadcn Primitives

**Files:**
- Modify: `resources/js/Pages/Jobs/Index.tsx`

**Interfaces:**
- Consumes: `@/Components/ui/button`, `@/Components/ui/input`, `@/Components/ui/select`, `@/Components/ui/table` (sub-project 1).
- Produces: nothing new for later tasks — `<Link>` targets (`jobs.create`, `jobs.show`, `jobs.import.create`, `jobs.export`) are unchanged.

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
                                        <SelectItem value="selesai">
                                            Selesai
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
                                                'jobs.import.create',
                                            )}
                                        >
                                            Import
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
                        </form>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nama Pekerjaan</TableHead>
                                        <TableHead>Klien</TableHead>
                                        <TableHead>Lokasi</TableHead>
                                        <TableHead>Periode PR</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {jobs.data.map((job) => (
                                        <TableRow key={job.id}>
                                            <TableCell>
                                                <Link
                                                    href={route(
                                                        'jobs.show',
                                                        job.id,
                                                    )}
                                                    className="text-pln-blue hover:text-pln-blue-dark"
                                                >
                                                    {job.nama_pekerjaan}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                {job.klien ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {job.lokasi ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {job.periods_count}
                                            </TableCell>
                                            <TableCell>
                                                {job.status === 'aktif'
                                                    ? 'Aktif'
                                                    : 'Selesai'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination links={jobs.links} />
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
git add resources/js/Pages/Jobs/Index.tsx
git commit -m "Migrate Job Index to shadcn Button/Input/Select/Table"
```

---

### Task 2: Migrate Jobs/Create.tsx to a Dialog + Add Toast

**Files:**
- Modify: `resources/js/Pages/Jobs/Create.tsx`
- Modify: `app/Http/Controllers/JobController.php` (`store()` only)
- Modify: `tests/Feature/Jobs/JobCrudTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/input`, `@/Components/ui/label`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page (also the destination of Renew's scenario (b), unchanged route).

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

    const close = () => router.visit(route('jobs.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title="Tambah Job" />

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
                            <DialogTitle>Tambah Job</DialogTitle>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <h3 className="text-sm font-semibold text-pln-navy">
                                Data Job
                            </h3>

                            <div>
                                <Label htmlFor="nama_pekerjaan">
                                    Nama Pekerjaan
                                </Label>
                                <Input
                                    id="nama_pekerjaan"
                                    className="mt-1"
                                    value={data.nama_pekerjaan}
                                    onChange={(e) =>
                                        setData(
                                            'nama_pekerjaan',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    autoFocus
                                />
                                {errors.nama_pekerjaan && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nama_pekerjaan}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="klien">Klien</Label>
                                <Input
                                    id="klien"
                                    className="mt-1"
                                    value={data.klien}
                                    onChange={(e) =>
                                        setData('klien', e.target.value)
                                    }
                                />
                                {errors.klien && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.klien}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="lokasi">Lokasi</Label>
                                <Input
                                    id="lokasi"
                                    className="mt-1"
                                    value={data.lokasi}
                                    onChange={(e) =>
                                        setData('lokasi', e.target.value)
                                    }
                                />
                                {errors.lokasi && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.lokasi}
                                    </p>
                                )}
                            </div>

                            <h3 className="mt-2 text-sm font-semibold text-pln-navy">
                                Periode PR Pertama
                            </h3>

                            <div>
                                <Label htmlFor="jenis_dokumen">
                                    Jenis Dokumen
                                </Label>
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
                                {errors.jenis_dokumen && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.jenis_dokumen}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="no_dokumen">
                                    No. Dokumen
                                </Label>
                                <Input
                                    id="no_dokumen"
                                    className="mt-1"
                                    value={data.no_dokumen}
                                    onChange={(e) =>
                                        setData(
                                            'no_dokumen',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.no_dokumen && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.no_dokumen}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="kode_po">
                                    Kode PO (opsional)
                                </Label>
                                <Input
                                    id="kode_po"
                                    className="mt-1"
                                    value={data.kode_po}
                                    onChange={(e) =>
                                        setData('kode_po', e.target.value)
                                    }
                                />
                                {errors.kode_po && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.kode_po}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="nilai_po">Nilai PO</Label>
                                <Input
                                    id="nilai_po"
                                    type="number"
                                    step="0.01"
                                    className="mt-1"
                                    value={data.nilai_po}
                                    onChange={(e) =>
                                        setData('nilai_po', e.target.value)
                                    }
                                    required
                                />
                                {errors.nilai_po && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nilai_po}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="tanggal_mulai">
                                        Tanggal Mulai
                                    </Label>
                                    <Input
                                        id="tanggal_mulai"
                                        type="date"
                                        className="mt-1"
                                        value={data.tanggal_mulai}
                                        onChange={(e) =>
                                            setData(
                                                'tanggal_mulai',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {errors.tanggal_mulai && (
                                        <p className="mt-2 text-sm text-destructive">
                                            {errors.tanggal_mulai}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="tanggal_selesai">
                                        Tanggal Selesai
                                    </Label>
                                    <Input
                                        id="tanggal_selesai"
                                        type="date"
                                        className="mt-1"
                                        value={data.tanggal_selesai}
                                        onChange={(e) =>
                                            setData(
                                                'tanggal_selesai',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {errors.tanggal_selesai && (
                                        <p className="mt-2 text-sm text-destructive">
                                            {errors.tanggal_selesai}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="jumlah_tk_rencana">
                                    Jumlah TK Rencana
                                </Label>
                                <Input
                                    id="jumlah_tk_rencana"
                                    type="number"
                                    className="mt-1"
                                    value={data.jumlah_tk_rencana}
                                    onChange={(e) =>
                                        setData(
                                            'jumlah_tk_rencana',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.jumlah_tk_rencana && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.jumlah_tk_rencana}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="keterangan">
                                    Keterangan (opsional)
                                </Label>
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
                                {errors.keterangan && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.keterangan}
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

- [ ] **Step 2: Add a flash message to `JobController::store()`**

Change:

```php
        return redirect()->route('jobs.index');
```

to:

```php
        return redirect()->route('jobs.index')->with('success', 'Job berhasil ditambahkan.');
```

- [ ] **Step 3: Update the tests**

In `tests/Feature/Jobs/JobCrudTest.php`, add `->assertSessionHas('success', 'Job berhasil ditambahkan.')` to both `test_admin_can_create_a_job_with_its_first_period` and `test_staff_input_can_create_a_job`, inserted right before their existing `assertRedirect(route('jobs.index'))` call.

- [ ] **Step 4: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Jobs/Create.tsx app/Http/Controllers/JobController.php tests/Feature/Jobs/JobCrudTest.php
git commit -m "Migrate Job Create to a Dialog and add a success toast"
```

---

### Task 3: Migrate Jobs/Show.tsx to a Wider Dialog + Add Toast

**Files:**
- Modify: `resources/js/Pages/Jobs/Show.tsx`
- Modify: `app/Http/Controllers/JobController.php` (`toggleStatus()` only)
- Modify: `tests/Feature/Jobs/JobCrudTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/table`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks (its "Perbarui PR" `<Link>` to `jobs.renew` is unchanged — Task 4 migrates that destination page).

- [ ] **Step 1: Rewrite `Show.tsx` as a wider Dialog**

```tsx
import { Button } from '@/Components/ui/button';
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
import { JobDetail, JobPeriodRow, PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

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

    const close = () => router.visit(route('jobs.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={job.nama_pekerjaan} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent
                    className="sm:max-w-3xl"
                    aria-describedby={undefined}
                >
                    <DialogHeader>
                        <DialogTitle>{job.nama_pekerjaan}</DialogTitle>
                    </DialogHeader>

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
                        <div className="flex gap-2">
                            {hasActivePeriod && (
                                <Button asChild>
                                    <Link href={route('jobs.renew', job.id)}>
                                        Perbarui PR
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" onClick={toggleStatus}>
                                {job.status === 'aktif'
                                    ? 'Tandai Selesai'
                                    : 'Tandai Aktif'}
                            </Button>
                        </div>
                    )}

                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-pln-navy">
                            Riwayat Periode PR
                        </h3>

                        <div className="max-h-80 overflow-y-auto overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Jenis</TableHead>
                                        <TableHead>No. Dokumen</TableHead>
                                        <TableHead>Periode</TableHead>
                                        <TableHead>Nilai PO</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {periods.map((period) => (
                                        <TableRow key={period.id}>
                                            <TableCell>
                                                {period.jenis_dokumen}
                                            </TableCell>
                                            <TableCell>
                                                {period.no_dokumen}
                                            </TableCell>
                                            <TableCell>
                                                {period.tanggal_mulai}
                                                {period.tanggal_selesai
                                                    ? ` s/d ${period.tanggal_selesai}`
                                                    : ''}
                                            </TableCell>
                                            <TableCell>
                                                {period.nilai_po.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {period.status}
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    href={route(
                                                        'job-periods.show',
                                                        period.id,
                                                    )}
                                                    className="text-pln-blue hover:underline"
                                                >
                                                    Detail
                                                </Link>
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

- [ ] **Step 2: Add a flash message to `JobController::toggleStatus()`**

Change:

```php
        return redirect()->route('jobs.show', $job);
```

to:

```php
        return redirect()->route('jobs.show', $job)->with('success', 'Status job berhasil diperbarui.');
```

- [ ] **Step 3: Update the tests**

In `tests/Feature/Jobs/JobCrudTest.php`, add `->assertSessionHas('success', 'Status job berhasil diperbarui.')` to `test_admin_can_toggle_job_status`, inserted right before its existing `->assertRedirect(route('jobs.show', $job))` call.

- [ ] **Step 4: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Jobs/Show.tsx app/Http/Controllers/JobController.php tests/Feature/Jobs/JobCrudTest.php
git commit -m "Migrate Job Show to a wider Dialog and add a success toast"
```

---

### Task 4: Migrate Jobs/Renew.tsx to a Dialog

**Files:**
- Modify: `resources/js/Pages/Jobs/Renew.tsx`

**Interfaces:**
- Consumes: `@/Components/ui/dialog` (sub-project 1).
- Produces: nothing consumed by later tasks — its three `<Link>`s to `jobs.periods.create`, `jobs.create`, and `job-periods.show` are unchanged targets.

- [ ] **Step 1: Rewrite `Renew.tsx` as a Dialog closing to `jobs.show`**

```tsx
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface RenewJob {
    id: number;
    nama_pekerjaan: string;
}

export default function Renew({
    job,
    activePeriodId,
}: PageProps<{ job: RenewJob; activePeriodId: number | null }>) {
    const close = () => router.visit(route('jobs.show', job.id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Perbarui PR - ${job.nama_pekerjaan}`} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent aria-describedby={undefined}>
                    <DialogHeader>
                        <DialogTitle>
                            Perbarui PR: {job.nama_pekerjaan}
                        </DialogTitle>
                    </DialogHeader>

                    <p className="text-sm text-slate-600">
                        Pilih salah satu skenario sesuai kondisi pembaruan
                        No. PR untuk pekerjaan ini:
                    </p>

                    <div className="space-y-4">
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
                                Pekerjaan yang benar-benar berbeda —
                                dibuat sebagai Job baru, tanpa relasi ke
                                Job ini.
                            </p>
                        </Link>

                        {activePeriodId && (
                            <Link
                                href={route(
                                    'job-periods.show',
                                    activePeriodId,
                                )}
                                className="block rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-pln-blue"
                            >
                                <h3 className="text-sm font-semibold text-pln-navy">
                                    (c) Assignment Saja
                                </h3>
                                <p className="mt-1 text-sm text-slate-600">
                                    Job & No. PR tidak berubah — kelola
                                    penugasan pekerja untuk periode
                                    aktif ini.
                                </p>
                            </Link>
                        )}
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
git add resources/js/Pages/Jobs/Renew.tsx
git commit -m "Migrate Job Renew picker to a Dialog"
```

---

### Task 5: Migrate Jobs/Periods/Create.tsx to a Dialog + Add Toast

**Files:**
- Modify: `resources/js/Pages/Jobs/Periods/Create.tsx`
- Modify: `app/Http/Controllers/JobPeriodController.php` (`store()` only)
- Modify: `tests/Feature/Jobs/JobPeriodRenewalTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/input`, `@/Components/ui/label`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page.

- [ ] **Step 1: Rewrite `Jobs/Periods/Create.tsx` as a Dialog closing to `jobs.show`**

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
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
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

    const close = () => router.visit(route('jobs.show', job.id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Periode PR Baru - ${job.nama_pekerjaan}`} />

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
                                Periode PR Baru: {job.nama_pekerjaan}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="jenis_dokumen">
                                    Jenis Dokumen
                                </Label>
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
                                {errors.jenis_dokumen && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.jenis_dokumen}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="no_dokumen">
                                    No. Dokumen
                                </Label>
                                <Input
                                    id="no_dokumen"
                                    className="mt-1"
                                    value={data.no_dokumen}
                                    onChange={(e) =>
                                        setData(
                                            'no_dokumen',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    autoFocus
                                />
                                {errors.no_dokumen && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.no_dokumen}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="kode_po">
                                    Kode PO (opsional)
                                </Label>
                                <Input
                                    id="kode_po"
                                    className="mt-1"
                                    value={data.kode_po}
                                    onChange={(e) =>
                                        setData('kode_po', e.target.value)
                                    }
                                />
                                {errors.kode_po && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.kode_po}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="nilai_po">Nilai PO</Label>
                                <Input
                                    id="nilai_po"
                                    type="number"
                                    step="0.01"
                                    className="mt-1"
                                    value={data.nilai_po}
                                    onChange={(e) =>
                                        setData('nilai_po', e.target.value)
                                    }
                                    required
                                />
                                {errors.nilai_po && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nilai_po}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="tanggal_mulai">
                                        Tanggal Mulai
                                    </Label>
                                    <Input
                                        id="tanggal_mulai"
                                        type="date"
                                        className="mt-1"
                                        value={data.tanggal_mulai}
                                        onChange={(e) =>
                                            setData(
                                                'tanggal_mulai',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    {errors.tanggal_mulai && (
                                        <p className="mt-2 text-sm text-destructive">
                                            {errors.tanggal_mulai}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="tanggal_selesai">
                                        Tanggal Selesai
                                    </Label>
                                    <Input
                                        id="tanggal_selesai"
                                        type="date"
                                        className="mt-1"
                                        value={data.tanggal_selesai}
                                        onChange={(e) =>
                                            setData(
                                                'tanggal_selesai',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {errors.tanggal_selesai && (
                                        <p className="mt-2 text-sm text-destructive">
                                            {errors.tanggal_selesai}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="jumlah_tk_rencana">
                                    Jumlah TK Rencana
                                </Label>
                                <Input
                                    id="jumlah_tk_rencana"
                                    type="number"
                                    className="mt-1"
                                    value={data.jumlah_tk_rencana}
                                    onChange={(e) =>
                                        setData(
                                            'jumlah_tk_rencana',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.jumlah_tk_rencana && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.jumlah_tk_rencana}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="keterangan">
                                    Keterangan (opsional)
                                </Label>
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
                                {errors.keterangan && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.keterangan}
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

- [ ] **Step 2: Add a flash message to `JobPeriodController::store()`**

Change:

```php
        return redirect()->route('jobs.show', $job);
```

to:

```php
        return redirect()->route('jobs.show', $job)->with('success', 'Periode PR baru berhasil ditambahkan.');
```

- [ ] **Step 3: Update the tests**

In `tests/Feature/Jobs/JobPeriodRenewalTest.php`, add `->assertSessionHas('success', 'Periode PR baru berhasil ditambahkan.')` to both `test_scenario_a_continuation_links_to_previous_period_and_closes_the_old_one` and `test_staff_input_can_access_the_renew_picker_and_submit_a_continuation`, inserted right before their existing `assertRedirect(route('jobs.show', $job))` calls.

- [ ] **Step 4: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Jobs/Periods/Create.tsx app/Http/Controllers/JobPeriodController.php tests/Feature/Jobs/JobPeriodRenewalTest.php
git commit -m "Migrate JobPeriod Create to a Dialog and add a success toast"
```

---

### Task 6: Migrate Jobs/Import.tsx to a Sheet + Add Toast

**Files:**
- Modify: `resources/js/Pages/Jobs/Import.tsx`
- Modify: `app/Http/Controllers/JobImportController.php` (`store()` only)
- Modify: `tests/Feature/Jobs/JobImportTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/sheet`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page.

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

    const close = () => router.visit(route('jobs.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title="Import Data Job & Periode PR" />

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
                        <SheetTitle>Import Data Job & Periode PR</SheetTitle>
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
                                                'jobs.import.errors',
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
                                Upload file Excel (.xlsx/.xls) dengan
                                kolom NO. DO/PR/WO, URAIAN PEKERJAAN,
                                JUMLAH TK, MULAI TANGGAL, S/D TANGGAL,
                                PO, NILAI PO, KETERANGAN. Setiap baris
                                akan dibuat sebagai Job baru.
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

- [ ] **Step 2: Add success/error flash messages to `JobImportController::store()`**

Change:

```php
        return redirect()->route('jobs.import.create')->with('job_import_result', [
            'created' => $import->created,
            'errorCount' => count($import->errors),
        ]);
```

to:

```php
        $errorCount = count($import->errors);

        $response = redirect()->route('jobs.import.create')
            ->with('job_import_result', [
                'created' => $import->created,
                'errorCount' => $errorCount,
            ]);

        if ($errorCount > 0) {
            return $response->with('error', "Import selesai dengan {$errorCount} baris gagal ({$import->created} berhasil). Lihat laporan error untuk detail.");
        }

        return $response->with('success', "Import selesai: {$import->created} berhasil, {$errorCount} gagal.");
```

- [ ] **Step 3: Update the tests**

In `tests/Feature/Jobs/JobImportTest.php`, in `test_valid_rows_are_imported_as_independent_jobs` (created=2, errorCount=0 for this test's fixture), add this assertion right after the existing `assertSessionHas('job_import_result', ...)` call:

```php
$response->assertSessionHas('success', 'Import selesai: 2 berhasil, 0 gagal.');
```

Also add a NEW test proving the `error`-flash path: import a file that produces at least one failing row (reuse the existing `makeXlsx()` helper and an error-inducing row shape already used by e.g. `test_row_with_no_recognizable_document_pattern_is_an_error` or `test_duplicate_no_dokumen_is_an_error` in this same file), and assert `assertSessionHas('error', ...)` with a message matching that fixture's actual created/errorCount numbers — NOT `assertSessionHas('success', ...)`.

- [ ] **Step 4: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Jobs/Import.tsx app/Http/Controllers/JobImportController.php tests/Feature/Jobs/JobImportTest.php
git commit -m "Migrate Job Import to a Sheet and add success/error toasts"
```

---

### Task 7: Migrate JobPeriods/Show.tsx to a Wider Dialog

**Files:**
- Modify: `resources/js/Pages/JobPeriods/Show.tsx`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/table` (sub-project 1).
- Produces: nothing consumed by later tasks — its `<Link>`s to `job-periods.assignments.create`, `assignments.edit`, `assignments.end.form` are unchanged targets.

- [ ] **Step 1: Rewrite `JobPeriods/Show.tsx` as a wider Dialog closing to `jobs.show`**

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
import { AssignmentRow, JobPeriodDetail, PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

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
    const close = () => router.visit(route('jobs.show', jobPeriod.job_id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Periode ${jobPeriod.no_dokumen}`} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent
                    className="sm:max-w-2xl"
                    aria-describedby={undefined}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {jobPeriod.job_nama_pekerjaan} —{' '}
                            {jobPeriod.jenis_dokumen} {jobPeriod.no_dokumen}
                        </DialogTitle>
                    </DialogHeader>

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

                    {canManage && (
                        <Link
                            href={route(
                                'job-periods.assignments.create',
                                jobPeriod.id,
                            )}
                            className="inline-block rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                        >
                            Assign Karyawan
                        </Link>
                    )}

                    {warningJumlahTk && (
                        <div className="rounded-lg bg-pln-yellow/20 p-3 text-sm text-pln-navy">
                            Jumlah TK aktif ({activeAssignmentCount}) tidak
                            sama dengan rencana (
                            {jobPeriod.jumlah_tk_rencana}).
                        </div>
                    )}

                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-pln-navy">
                            Daftar Penugasan
                        </h3>

                        <div className="max-h-80 overflow-y-auto overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Karyawan</TableHead>
                                        <TableHead>Periode</TableHead>
                                        <TableHead>Status</TableHead>
                                        {canManage && <TableHead />}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell>
                                                {assignment.employee_nama}
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
                                            {canManage &&
                                                (assignment.is_current &&
                                                assignment.status ===
                                                    'aktif' ? (
                                                    <TableCell>
                                                        <Link
                                                            href={route(
                                                                'assignments.edit',
                                                                assignment.id,
                                                            )}
                                                            className="text-pln-blue hover:underline"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <Link
                                                            href={route(
                                                                'assignments.end.form',
                                                                assignment.id,
                                                            )}
                                                            className="ml-3 text-red-600 hover:underline"
                                                        >
                                                            Akhiri
                                                        </Link>
                                                    </TableCell>
                                                ) : (
                                                    <TableCell />
                                                ))}
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
git add resources/js/Pages/JobPeriods/Show.tsx
git commit -m "Migrate JobPeriod Show to a wider Dialog"
```

---

### Task 8: Migrate Assignments/Create.tsx to a Dialog + Add Toast

**Files:**
- Modify: `resources/js/Pages/Assignments/Create.tsx`
- Modify: `app/Http/Controllers/AssignmentController.php` (`store()` only)
- Modify: `tests/Feature/Assignments/AssignmentCreateTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/input`, `@/Components/ui/label`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page.

- [ ] **Step 1: Rewrite `Assignments/Create.tsx` as a Dialog closing to `job-periods.show`**

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
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
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

    const close = () =>
        router.visit(route('job-periods.show', jobPeriod.id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Assign Karyawan - ${jobPeriod.no_dokumen}`} />

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
                                Assign Karyawan: {jobPeriod.no_dokumen}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="employee_id">
                                    Karyawan
                                </Label>
                                <select
                                    id="employee_id"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.employee_id}
                                    onChange={(e) =>
                                        setData(
                                            'employee_id',
                                            e.target.value,
                                        )
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
                                {errors.employee_id && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.employee_id}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tanggal_mulai">
                                    Tanggal Mulai
                                </Label>
                                <Input
                                    id="tanggal_mulai"
                                    type="date"
                                    className="mt-1"
                                    value={data.tanggal_mulai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_mulai',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.tanggal_mulai && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tanggal_mulai}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tanggal_selesai">
                                    Tanggal Selesai (opsional)
                                </Label>
                                <Input
                                    id="tanggal_selesai"
                                    type="date"
                                    className="mt-1"
                                    value={data.tanggal_selesai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_selesai',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.tanggal_selesai && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tanggal_selesai}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tarif_jual">
                                    Tarif Jual (opsional)
                                </Label>
                                <Input
                                    id="tarif_jual"
                                    type="number"
                                    step="0.01"
                                    className="mt-1"
                                    value={data.tarif_jual}
                                    onChange={(e) =>
                                        setData(
                                            'tarif_jual',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.tarif_jual && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tarif_jual}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tarif_bayar">
                                    Tarif Bayar (opsional)
                                </Label>
                                <Input
                                    id="tarif_bayar"
                                    type="number"
                                    step="0.01"
                                    className="mt-1"
                                    value={data.tarif_bayar}
                                    onChange={(e) =>
                                        setData(
                                            'tarif_bayar',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.tarif_bayar && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tarif_bayar}
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

- [ ] **Step 2: Add a flash message to `AssignmentController::store()`**

Change:

```php
        return redirect()->route('job-periods.show', $jobPeriod);
```

to:

```php
        return redirect()->route('job-periods.show', $jobPeriod)->with('success', 'Karyawan berhasil ditugaskan.');
```

- [ ] **Step 3: Update the tests**

In `tests/Feature/Assignments/AssignmentCreateTest.php`, add `->assertSessionHas('success', 'Karyawan berhasil ditugaskan.')` to `test_admin_can_assign_an_active_employee_to_an_active_period` and `test_can_reassign_the_same_employee_after_their_previous_assignment_ended`, inserted right before their existing `assertRedirect(route('job-periods.show', $period))` calls. For `test_same_employee_can_have_current_assignments_on_two_different_job_periods` (which makes two separate requests, `$responseA` and `$responseB`, each already asserting its own redirect), add the same assertion to BOTH `$responseA` and `$responseB` before their respective `assertRedirect` calls.

- [ ] **Step 4: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Assignments/Create.tsx app/Http/Controllers/AssignmentController.php tests/Feature/Assignments/AssignmentCreateTest.php
git commit -m "Migrate Assignment Create to a Dialog and add a success toast"
```

---

### Task 9: Migrate Assignments/Edit.tsx to a Dialog + Add Toast

**Files:**
- Modify: `resources/js/Pages/Assignments/Edit.tsx`
- Modify: `app/Http/Controllers/AssignmentController.php` (`edit()` and `update()` only)
- Modify: `tests/Feature/Assignments/AssignmentUpdateTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/input`, `@/Components/ui/label`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page.

- [ ] **Step 1: Add `job_period_id` to `AssignmentController::edit()`'s props**

In `app/Http/Controllers/AssignmentController.php`, change `edit()`'s returned array from:

```php
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
```

to:

```php
        return Inertia::render('Assignments/Edit', [
            'assignment' => [
                'id' => $assignment->id,
                'job_period_id' => $assignment->job_period_id,
                'employee_nama' => $assignment->employee->nama,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $assignment->tanggal_selesai?->format('Y-m-d'),
                'tarif_jual' => $assignment->tarif_jual,
                'tarif_bayar' => $assignment->tarif_bayar,
            ],
        ]);
```

- [ ] **Step 2: Rewrite `Assignments/Edit.tsx` as a Dialog closing to `job-periods.show`**

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
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EditAssignmentInfo {
    id: number;
    job_period_id: number;
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

    const close = () =>
        router.visit(route('job-periods.show', assignment.job_period_id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Edit Penugasan - ${assignment.employee_nama}`} />

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
                                Edit Penugasan: {assignment.employee_nama}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="tanggal_mulai">
                                    Tanggal Mulai
                                </Label>
                                <Input
                                    id="tanggal_mulai"
                                    type="date"
                                    className="mt-1"
                                    value={data.tanggal_mulai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_mulai',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.tanggal_mulai && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tanggal_mulai}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tanggal_selesai">
                                    Tanggal Selesai (opsional)
                                </Label>
                                <Input
                                    id="tanggal_selesai"
                                    type="date"
                                    className="mt-1"
                                    value={data.tanggal_selesai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_selesai',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.tanggal_selesai && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tanggal_selesai}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tarif_jual">
                                    Tarif Jual (opsional)
                                </Label>
                                <Input
                                    id="tarif_jual"
                                    type="number"
                                    step="0.01"
                                    className="mt-1"
                                    value={data.tarif_jual}
                                    onChange={(e) =>
                                        setData(
                                            'tarif_jual',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.tarif_jual && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tarif_jual}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tarif_bayar">
                                    Tarif Bayar (opsional)
                                </Label>
                                <Input
                                    id="tarif_bayar"
                                    type="number"
                                    step="0.01"
                                    className="mt-1"
                                    value={data.tarif_bayar}
                                    onChange={(e) =>
                                        setData(
                                            'tarif_bayar',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.tarif_bayar && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tarif_bayar}
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

- [ ] **Step 3: Add a flash message to `AssignmentController::update()`**

Change:

```php
        return redirect()->route('job-periods.show', $assignment->job_period_id);
```

to:

```php
        return redirect()->route('job-periods.show', $assignment->job_period_id)->with('success', 'Penugasan berhasil diperbarui.');
```

- [ ] **Step 4: Update the tests**

In `tests/Feature/Assignments/AssignmentUpdateTest.php`, add `->assertSessionHas('success', 'Penugasan berhasil diperbarui.')` to both `test_changing_dates_creates_a_new_version_and_closes_the_old_one` and `test_changing_only_tarif_updates_in_place_without_a_new_version`, inserted right before their existing `assertRedirect` calls (verify each test's exact redirect assertion text before inserting — `test_changing_dates_creates_a_new_version_and_closes_the_old_one` asserts `assertRedirect(route('job-periods.show', $old->job_period_id))`; if `test_changing_only_tarif_updates_in_place_without_a_new_version` doesn't have its own `assertRedirect` call, capture its response and assert directly on it, following the same adaptation pattern used in the Employee module's equivalent situation).

- [ ] **Step 5: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Assignments/Edit.tsx app/Http/Controllers/AssignmentController.php tests/Feature/Assignments/AssignmentUpdateTest.php
git commit -m "Migrate Assignment Edit to a Dialog and add a success toast"
```

---

### Task 10: Migrate Assignments/End.tsx to a Dialog + Add Toast

**Files:**
- Modify: `resources/js/Pages/Assignments/End.tsx`
- Modify: `app/Http/Controllers/AssignmentController.php` (`endForm()` and `end()` only)
- Modify: `tests/Feature/Assignments/AssignmentEndTest.php`

**Interfaces:**
- Consumes: `@/Components/ui/dialog`, `@/Components/ui/input`, `@/Components/ui/label`, `@/Components/ui/button` (sub-project 1).
- Produces: nothing consumed by later tasks — leaf page, and this is the last task in this sub-project.

- [ ] **Step 1: Add `job_period_id` to `AssignmentController::endForm()`'s props**

Change:

```php
        return Inertia::render('Assignments/End', [
            'assignment' => [
                'id' => $assignment->id,
                'employee_nama' => $assignment->employee->nama,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
            ],
        ]);
```

to:

```php
        return Inertia::render('Assignments/End', [
            'assignment' => [
                'id' => $assignment->id,
                'job_period_id' => $assignment->job_period_id,
                'employee_nama' => $assignment->employee->nama,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
            ],
        ]);
```

- [ ] **Step 2: Rewrite `Assignments/End.tsx` as a Dialog closing to `job-periods.show`**

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
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EndAssignmentInfo {
    id: number;
    job_period_id: number;
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

    const close = () =>
        router.visit(route('job-periods.show', assignment.job_period_id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Akhiri Penugasan - ${assignment.employee_nama}`} />

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
                                Akhiri Penugasan: {assignment.employee_nama}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="mt-4">
                            <Label htmlFor="tanggal_selesai">
                                Tanggal Akhir
                            </Label>
                            <Input
                                id="tanggal_selesai"
                                type="date"
                                className="mt-1"
                                value={data.tanggal_selesai}
                                onChange={(e) =>
                                    setData(
                                        'tanggal_selesai',
                                        e.target.value,
                                    )
                                }
                                min={assignment.tanggal_mulai}
                                required
                                autoFocus
                            />
                            {errors.tanggal_selesai && (
                                <p className="mt-2 text-sm text-destructive">
                                    {errors.tanggal_selesai}
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
                                Akhiri Penugasan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
```

- [ ] **Step 3: Add a flash message to `AssignmentController::end()`**

Change:

```php
        return redirect()->route('job-periods.show', $assignment->job_period_id);
```

to:

```php
        return redirect()->route('job-periods.show', $assignment->job_period_id)->with('success', 'Penugasan berhasil diakhiri.');
```

- [ ] **Step 4: Update the tests**

In `tests/Feature/Assignments/AssignmentEndTest.php`, add `->assertSessionHas('success', 'Penugasan berhasil diakhiri.')` to `test_ending_sets_status_selesai_and_keeps_is_current_true_with_no_new_row`, inserted right before its existing `assertRedirect(route('job-periods.show', $assignment->job_period_id))` call.

- [ ] **Step 5: Verify and commit**

```bash
npx tsc --noEmit
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add resources/js/Pages/Assignments/End.tsx app/Http/Controllers/AssignmentController.php tests/Feature/Assignments/AssignmentEndTest.php
git commit -m "Migrate Assignment End to a Dialog and add a success toast"
```
