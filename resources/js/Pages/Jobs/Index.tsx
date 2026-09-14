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
