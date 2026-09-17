import Pagination from '@/Components/Pagination';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
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
import {
    Download,
    Eye,
    MoreHorizontal,
    Plus,
    Search,
    Upload,
    UserPlus,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Filters {
    search?: string;
    status?: string;
}

export default function Index({
    jobs,
    filters,
    perPageOptions,
    canManage,
}: PageProps<{
    jobs: Paginated<JobRow>;
    filters: Filters;
    perPageOptions: number[];
    canManage: boolean;
}>) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilters: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            route('jobs.index'),
            { search, status, per_page: jobs.per_page },
            { preserveState: true, replace: true },
        );
    };

    const changePerPage = (value: string) => {
        router.get(
            route('jobs.index'),
            { search, status, per_page: value },
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
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
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

                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Per Halaman
                                </label>
                                <Select
                                    value={String(jobs.per_page)}
                                    onValueChange={changePerPage}
                                >
                                    <SelectTrigger className="mt-1 w-[100px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {perPageOptions.map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={String(option)}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button type="submit">
                                <Search className="mr-2 h-4 w-4" />
                                Terapkan
                            </Button>

                            {canManage && (
                                <div className="ml-auto flex flex-wrap gap-2">
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={route(
                                                'jobs.import.create',
                                            )}
                                        >
                                            <Upload className="mr-2 h-4 w-4" />
                                            Import
                                        </Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={route(
                                                'assignments.import.create',
                                            )}
                                        >
                                            <UserPlus className="mr-2 h-4 w-4" />
                                            Import Penugasan
                                        </Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <a href={route('jobs.export')}>
                                            <Download className="mr-2 h-4 w-4" />
                                            Export
                                        </a>
                                    </Button>
                                    <Button asChild>
                                        <Link href={route('jobs.create')}>
                                            <Plus className="mr-2 h-4 w-4" />
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
                                        <TableHead className="w-12 py-2">
                                            #
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Nama Pekerjaan
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Klien
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Lokasi
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Periode PR
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Status
                                        </TableHead>
                                        <TableHead className="py-2" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {jobs.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={7}
                                                className="py-2 text-center text-slate-500"
                                            >
                                                Tidak ada data job.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {jobs.data.map((job, index) => (
                                        <TableRow key={job.id}>
                                            <TableCell className="py-2 text-slate-500">
                                                {(jobs.from ?? 1) + index}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {job.nama_pekerjaan}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {job.klien ?? '—'}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {job.lokasi ?? '—'}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {job.periods_count}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        job.status ===
                                                        'aktif'
                                                            ? 'border-transparent bg-green-100 text-green-700 hover:bg-green-100'
                                                            : 'border-transparent bg-slate-100 text-slate-600 hover:bg-slate-100'
                                                    }
                                                >
                                                    {job.status === 'aktif'
                                                        ? 'Aktif'
                                                        : 'Selesai'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="py-2 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
                                                            <span className="sr-only">
                                                                Aksi
                                                            </span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={route(
                                                                    'jobs.show',
                                                                    job.id,
                                                                )}
                                                            >
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                Detail
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
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
