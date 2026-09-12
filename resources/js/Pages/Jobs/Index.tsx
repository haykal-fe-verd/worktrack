import Pagination from '@/Components/Pagination';
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
                                    <option value="selesai">Selesai</option>
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
                                        href={route('jobs.import.create')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Import
                                    </Link>
                                    <a
                                        href={route('jobs.export')}
                                        className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                    >
                                        Export
                                    </a>
                                    <Link
                                        href={route('jobs.create')}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Tambah Job
                                    </Link>
                                </div>
                            )}
                        </form>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Nama Pekerjaan
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Klien
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Lokasi
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Periode PR
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {jobs.data.map((job) => (
                                        <tr key={job.id}>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                                <Link
                                                    href={route(
                                                        'jobs.show',
                                                        job.id,
                                                    )}
                                                    className="text-pln-blue hover:text-pln-blue-dark"
                                                >
                                                    {job.nama_pekerjaan}
                                                </Link>
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {job.klien ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {job.lokasi ?? '—'}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {job.periods_count}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {job.status === 'aktif'
                                                    ? 'Aktif'
                                                    : 'Selesai'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Pagination links={jobs.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
