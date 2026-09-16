import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
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
import { EmployeeRow, PageProps, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    Download,
    Eye,
    MoreHorizontal,
    Pencil,
    Plus,
    Power,
    PowerOff,
    Search,
    Upload,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Filters {
    search?: string;
    status?: string;
}

export default function Index({
    employees,
    filters,
    perPageOptions,
    canManage,
}: PageProps<{
    employees: Paginated<EmployeeRow>;
    filters: Filters;
    perPageOptions: number[];
    canManage: boolean;
}>) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [togglingId, setTogglingId] = useState<number | null>(null);
    const [confirmingEmployee, setConfirmingEmployee] =
        useState<EmployeeRow | null>(null);
    const [confirmDialogOpen, setConfirmDialogOpen] = useState(false);

    const applyFilters: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            route('employees.index'),
            { search, status, per_page: employees.per_page },
            { preserveState: true, replace: true },
        );
    };

    const changePerPage = (value: string) => {
        router.get(
            route('employees.index'),
            { search, status, per_page: value },
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
            {
                onFinish: () => {
                    setTogglingId(null);
                    setConfirmDialogOpen(false);
                },
            },
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

                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Per Halaman
                                </label>
                                <Select
                                    value={String(employees.per_page)}
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
                                <div className="ml-auto flex gap-2">
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={route(
                                                'employees.import.create',
                                            )}
                                        >
                                            <Upload className="mr-2 h-4 w-4" />
                                            Import
                                        </Link>
                                    </Button>
                                    <Button variant="outline" asChild>
                                        <a href={route('employees.export')}>
                                            <Download className="mr-2 h-4 w-4" />
                                            Export
                                        </a>
                                    </Button>
                                    <Button asChild>
                                        <Link
                                            href={route('employees.create')}
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
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
                                        <TableHead className="w-12 py-2">
                                            #
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Nama
                                        </TableHead>
                                        <TableHead className="py-2">
                                            NIK
                                        </TableHead>
                                        <TableHead className="py-2">
                                            No. Rekening
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Status
                                        </TableHead>
                                        <TableHead className="py-2" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {employees.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="py-2 text-center text-slate-500"
                                            >
                                                Tidak ada data karyawan.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {employees.data.map((employee, index) => (
                                        <TableRow key={employee.id}>
                                            <TableCell className="py-2 text-slate-500">
                                                {(employees.from ?? 1) +
                                                    index}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {employee.nama}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {employee.nik}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {employee.no_rekening}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        employee.status ===
                                                        'aktif'
                                                            ? 'border-transparent bg-green-100 text-green-700 hover:bg-green-100'
                                                            : 'border-transparent bg-red-100 text-red-700 hover:bg-red-100'
                                                    }
                                                >
                                                    {employee.status ===
                                                    'aktif'
                                                        ? 'Aktif'
                                                        : 'Non-aktif'}
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
                                                                    'employees.show',
                                                                    employee.id,
                                                                )}
                                                            >
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                Detail
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        {canManage && (
                                                            <DropdownMenuItem
                                                                asChild
                                                            >
                                                                <Link
                                                                    href={route(
                                                                        'employees.edit',
                                                                        employee.id,
                                                                    )}
                                                                >
                                                                    <Pencil className="mr-2 h-4 w-4" />
                                                                    Edit
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        )}
                                                        {canManage && (
                                                            <DropdownMenuItem
                                                                disabled={
                                                                    togglingId ===
                                                                    employee.id
                                                                }
                                                                onSelect={() => {
                                                                    setConfirmingEmployee(
                                                                        employee,
                                                                    );
                                                                    setConfirmDialogOpen(
                                                                        true,
                                                                    );
                                                                }}
                                                            >
                                                                {employee.status ===
                                                                'aktif' ? (
                                                                    <PowerOff className="mr-2 h-4 w-4" />
                                                                ) : (
                                                                    <Power className="mr-2 h-4 w-4" />
                                                                )}
                                                                {employee.status ===
                                                                'aktif'
                                                                    ? 'Nonaktifkan'
                                                                    : 'Aktifkan'}
                                                            </DropdownMenuItem>
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination links={employees.links} />
                    </div>
                </div>
            </div>

            <AlertDialog
                open={confirmDialogOpen}
                onOpenChange={setConfirmDialogOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {confirmingEmployee?.status === 'aktif'
                                ? 'Nonaktifkan karyawan?'
                                : 'Aktifkan karyawan?'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {confirmingEmployee?.status === 'aktif'
                                ? `Status ${confirmingEmployee?.nama} akan diubah menjadi Non-aktif.`
                                : `Status ${confirmingEmployee?.nama} akan diubah menjadi Aktif.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={togglingId !== null}
                            onClick={() =>
                                confirmingEmployee &&
                                toggleStatus(confirmingEmployee.id)
                            }
                        >
                            Lanjutkan
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AuthenticatedLayout>
    );
}
