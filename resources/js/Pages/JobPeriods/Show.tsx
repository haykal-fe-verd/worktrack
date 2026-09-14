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
import { AssignmentRow, JobPeriodDetail, PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

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
    const close = () => router.visit(route('jobs.show', jobPeriod.job_id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Periode ${jobPeriod.no_dokumen}`} />

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
                        <DialogTitle>
                            {jobPeriod.job_nama_pekerjaan} —{' '}
                            {jobPeriod.jenis_dokumen} {jobPeriod.no_dokumen}
                        </DialogTitle>
                    </DialogHeader>

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
                        <Link
                            href={route(
                                'job-periods.assignments.create',
                                jobPeriod.id,
                            )}
                            className="inline-block rounded-md bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                        >
                            Assign Karyawan
                        </Link>
                    )}

                    {warningJumlahTk && (
                        <div className="rounded-lg bg-pln-yellow/20 p-3 text-sm text-pln-navy">
                            Jumlah TK aktif ({activeAssignmentCount}) tidak
                            sama dengan rencana (
                            {jobPeriod.jumlah_tk_rencana}).
                        </div>
                    )}

                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-pln-navy">
                            Daftar Penugasan
                        </h3>

                        <div className="max-h-80 overflow-y-auto overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Karyawan</TableHead>
                                        <TableHead>Periode</TableHead>
                                        <TableHead>Status</TableHead>
                                        {canManage && <TableHead />}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assignments.map((assignment) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell>
                                                {assignment.employee_nama}
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
                                            {canManage &&
                                                (assignment.is_current &&
                                                assignment.status ===
                                                    'aktif' ? (
                                                    <TableCell>
                                                        <Link
                                                            href={route(
                                                                'assignments.edit',
                                                                assignment.id,
                                                            )}
                                                            className="text-pln-blue hover:underline"
                                                        >
                                                            Edit
                                                        </Link>
                                                        <Link
                                                            href={route(
                                                                'assignments.end.form',
                                                                assignment.id,
                                                            )}
                                                            className="ml-3 text-red-600 hover:underline"
                                                        >
                                                            Akhiri
                                                        </Link>
                                                    </TableCell>
                                                ) : (
                                                    <TableCell />
                                                ))}
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
