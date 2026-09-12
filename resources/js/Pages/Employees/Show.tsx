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
import { EmployeeAssignmentRow, EmployeeDetail, PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';

export default function Show({
    employee,
    assignments,
}: PageProps<{
    employee: EmployeeDetail;
    assignments: EmployeeAssignmentRow[];
}>) {
    const close = () => router.visit(route('employees.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Karyawan
                </h2>
            }
        >
            <Head title={employee.nama} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{employee.nama}</DialogTitle>
                    </DialogHeader>

                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-slate-500">NIK</dt>
                            <dd className="font-medium">{employee.nik}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">No. Rekening</dt>
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

                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-pln-navy">
                            Riwayat Penugasan
                        </h3>

                        <div className="max-h-80 overflow-y-auto overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Job</TableHead>
                                        <TableHead>No. Dokumen</TableHead>
                                        <TableHead>Periode</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell>
                                                {
                                                    assignment.job_nama_pekerjaan
                                                }
                                            </TableCell>
                                            <TableCell>
                                                {assignment.no_dokumen}
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
