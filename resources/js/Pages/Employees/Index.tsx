import Pagination from '@/Components/Pagination';
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
import { EmployeeRow, PageProps, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
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
    const [togglingId, setTogglingId] = useState<number | null>(null);

    const applyFilters: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            route('employees.index'),
            { search, status },
            { preserveState: true, replace: true },
        );
    };

    const toggleStatus = (employeeId: number) => {
        if (togglingId !== null) {
            return;
        }

        setTogglingId(employeeId);
        router.patch(
            route('employees.toggle-status', employeeId),
            {},
            { onFinish: () => setTogglingId(null) },
        );
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
                                    {employees.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={canManage ? 5 : 4}
                                                className="text-center text-slate-500"
                                            >
                                                Tidak ada data karyawan.
                                            </TableCell>
                                        </TableRow>
                                    )}
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
                                                                        'employees.edit',
                                                                        employee.id,
                                                                    )}
                                                                >
                                                                    Edit
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                disabled={
                                                                    togglingId ===
                                                                    employee.id
                                                                }
                                                                onSelect={() =>
                                                                    toggleStatus(
                                                                        employee.id,
                                                                    )
                                                                }
                                                            >
                                                                {employee.status ===
                                                                'aktif'
                                                                    ? 'Nonaktifkan'
                                                                    : 'Aktifkan'}
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
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
