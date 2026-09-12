import Pagination from '@/Components/Pagination';
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
                                    <option value="non_aktif">
                                        Non-aktif
                                    </option>
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
                                        href={route('employees.import.create')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Import
                                    </Link>
                                    <a
                                        href={route('employees.export')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Export
                                    </a>
                                    <Link
                                        href={route('employees.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Karyawan
                                    </Link>
                                </div>
                            )}
                        </form>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Nama
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            NIK
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            No. Rekening
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Status
                                        </th>
                                        {canManage && (
                                            <th className="px-3 py-2" />
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {employees.data.map((employee) => (
                                        <tr key={employee.id}>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                                <Link
                                                    href={route(
                                                        'employees.show',
                                                        employee.id,
                                                    )}
                                                    className="text-pln-blue hover:underline"
                                                >
                                                    {employee.nama}
                                                </Link>
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {employee.nik}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {employee.no_rekening}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {employee.status ===
                                                'aktif'
                                                    ? 'Aktif'
                                                    : 'Non-aktif'}
                                            </td>
                                            {canManage && (
                                                <td className="whitespace-nowrap px-3 py-2 text-right text-sm">
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
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Pagination links={employees.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
