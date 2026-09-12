import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EmployeeAssignmentRow, EmployeeDetail, PageProps } from '@/types';
import { Head } from '@inertiajs/react';

export default function Show({
    employee,
    assignments,
}: PageProps<{
    employee: EmployeeDetail;
    assignments: EmployeeAssignmentRow[];
    canManage: boolean;
}>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    {employee.nama}
                </h2>
            }
        >
            <Head title={employee.nama} />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <dl className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt className="text-slate-500">NIK</dt>
                                <dd className="font-medium">
                                    {employee.nik}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-slate-500">
                                    No. Rekening
                                </dt>
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
                    </div>

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="mb-4 text-sm font-semibold text-pln-navy">
                            Riwayat Penugasan
                        </h3>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Job
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            No. Dokumen
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Periode
                                        </th>
                                        <th className="whitespace-nowrap px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {assignments.map((assignment) => (
                                        <tr key={assignment.id}>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-900">
                                                {
                                                    assignment.job_nama_pekerjaan
                                                }
                                            </td>
                                            <td className="whitespace-nowrap px-3 py-2 text-sm text-gray-500">
                                                {assignment.no_dokumen}
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
