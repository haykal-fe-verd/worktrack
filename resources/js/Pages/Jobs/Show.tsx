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

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    {job.nama_pekerjaan}
                </h2>
            }
        >
            <Head title={job.nama_pekerjaan} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
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
                            <div className="mt-4 flex gap-2">
                                {hasActivePeriod && (
                                    <Link
                                        href={route('jobs.renew', job.id)}
                                        className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                    >
                                        Perbarui PR
                                    </Link>
                                )}
                                <button
                                    type="button"
                                    onClick={toggleStatus}
                                    className="rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                >
                                    {job.status === 'aktif'
                                        ? 'Tandai Selesai'
                                        : 'Tandai Aktif'}
                                </button>
                            </div>
                        )}
                    </div>

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-sm font-semibold text-pln-navy">
                                Riwayat Periode PR
                            </h3>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Jenis
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            No. Dokumen
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Periode
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Nilai PO
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {periods.map((period) => (
                                        <tr key={period.id}>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                                {period.jenis_dokumen}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {period.no_dokumen}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {period.tanggal_mulai}
                                                {period.tanggal_selesai
                                                    ? ` s/d ${period.tanggal_selesai}`
                                                    : ''}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {period.nilai_po.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {period.status}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
