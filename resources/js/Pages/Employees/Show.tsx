import { Badge } from '@/Components/ui/badge';
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

const ASSIGNMENT_STATUS_LABELS: Record<string, string> = {
    aktif: 'Aktif',
    selesai: 'Selesai',
    diperbarui: 'Diperbarui',
};

const ASSIGNMENT_STATUS_CLASSES: Record<string, string> = {
    aktif: 'border-transparent bg-green-100 text-green-700 hover:bg-green-100',
    selesai:
        'border-transparent bg-slate-100 text-slate-600 hover:bg-slate-100',
    diperbarui:
        'border-transparent bg-blue-100 text-blue-700 hover:bg-blue-100',
};

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
                <DialogContent
                    className="sm:max-w-2xl"
                    aria-describedby={undefined}
                >
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
                            <dt className="text-slate-500">Nama Bank</dt>
                            <dd className="font-medium">
                                {employee.nama_bank ?? '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Status</dt>
                            <dd className="font-medium">
                                <Badge
                                    variant="outline"
                                    className={
                                        employee.status === 'aktif'
                                            ? 'border-transparent bg-green-100 text-green-700 hover:bg-green-100'
                                            : 'border-transparent bg-red-100 text-red-700 hover:bg-red-100'
                                    }
                                >
                                    {employee.status === 'aktif'
                                        ? 'Aktif'
                                        : 'Non-aktif'}
                                </Badge>
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
                                        <TableHead className="w-12 py-2">
                                            #
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Job
                                        </TableHead>
                                        <TableHead className="py-2">
                                            No. Dokumen
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Periode
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Status
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assignments.map((assignment, index) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell className="py-2 text-slate-500">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {
                                                    assignment.job_nama_pekerjaan
                                                }
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {assignment.no_dokumen}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {assignment.tanggal_mulai}
                                                {assignment.tanggal_selesai
                                                    ? ` s/d ${assignment.tanggal_selesai}`
                                                    : ''}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        ASSIGNMENT_STATUS_CLASSES[
                                                            assignment
                                                                .status
                                                        ]
                                                    }
                                                >
                                                    {ASSIGNMENT_STATUS_LABELS[
                                                        assignment.status
                                                    ] ?? assignment.status}
                                                </Badge>
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
