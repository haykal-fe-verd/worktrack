import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { AssignmentRow, JobPeriodDetail, PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

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
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    {jobPeriod.job_nama_pekerjaan} — {jobPeriod.jenis_dokumen}{' '}
                    {jobPeriod.no_dokumen}
                </h2>
            }
        >
            <Head title={`Periode ${jobPeriod.no_dokumen}`} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
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
                            <div className="mt-4">
                                <Link
                                    href={route(
                                        'job-periods.assignments.create',
                                        jobPeriod.id,
                                    )}
                                    className="rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                                >
                                    Assign Karyawan
                                </Link>
                            </div>
                        )}

                        {warningJumlahTk && (
                            <div className="mt-4 rounded-lg bg-pln-yellow/20 p-3 text-sm text-pln-navy">
                                Jumlah TK aktif ({activeAssignmentCount}) tidak
                                sama dengan rencana (
                                {jobPeriod.jumlah_tk_rencana}).
                            </div>
                        )}
                    </div>

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="mb-4 text-sm font-semibold text-pln-navy">
                            Daftar Penugasan
                        </h3>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Karyawan
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Periode
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
                                    {assignments.map((assignment) => (
                                        <tr key={assignment.id}>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                                {assignment.employee_nama}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {assignment.tanggal_mulai}
                                                {assignment.tanggal_selesai
                                                    ? ` s/d ${assignment.tanggal_selesai}`
                                                    : ''}
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {assignment.status}
                                            </td>
                                            {canManage &&
                                                (assignment.is_current ? (
                                                    <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                        <Link
                                                            href={route(
                                                                'assignments.edit',
                                                                assignment.id,
                                                            )}
                                                            className="text-pln-blue hover:underline"
                                                        >
                                                            Edit
                                                        </Link>
                                                    </td>
                                                ) : (
                                                    <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500" />
                                                ))}
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
