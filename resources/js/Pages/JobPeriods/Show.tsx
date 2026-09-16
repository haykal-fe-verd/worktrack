import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
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
import { MoreHorizontal, Pencil, UserPlus, XCircle } from 'lucide-react';

const PERIOD_STATUS_LABELS: Record<string, string> = {
    aktif: 'Aktif',
    berakhir: 'Berakhir',
    diperbarui: 'Diperbarui',
};

const PERIOD_STATUS_CLASSES: Record<string, string> = {
    aktif: 'border-transparent bg-green-100 text-green-700 hover:bg-green-100',
    berakhir:
        'border-transparent bg-slate-100 text-slate-600 hover:bg-slate-100',
    diperbarui:
        'border-transparent bg-blue-100 text-blue-700 hover:bg-blue-100',
};

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
                                <Badge
                                    variant="outline"
                                    className={
                                        PERIOD_STATUS_CLASSES[
                                            jobPeriod.status
                                        ]
                                    }
                                >
                                    {PERIOD_STATUS_LABELS[
                                        jobPeriod.status
                                    ] ?? jobPeriod.status}
                                </Badge>
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
                        <Button asChild>
                            <Link
                                href={route(
                                    'job-periods.assignments.create',
                                    jobPeriod.id,
                                )}
                            >
                                <UserPlus className="mr-2 h-4 w-4" />
                                Assign Karyawan
                            </Link>
                        </Button>
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
                                        <TableHead className="w-12 py-2">
                                            #
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Karyawan
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Periode
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Status
                                        </TableHead>
                                        {canManage && (
                                            <TableHead className="py-2" />
                                        )}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {assignments.map((assignment, index) => (
                                        <TableRow key={assignment.id}>
                                            <TableCell className="py-2 text-slate-500">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {assignment.employee_nama}
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
                                            {canManage &&
                                                (assignment.is_current &&
                                                assignment.status ===
                                                    'aktif' ? (
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
                                                                            'assignments.edit',
                                                                            assignment.id,
                                                                        )}
                                                                    >
                                                                        <Pencil className="mr-2 h-4 w-4" />
                                                                        Edit
                                                                    </Link>
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem
                                                                    asChild
                                                                    className="text-red-600 focus:text-red-600"
                                                                >
                                                                    <Link
                                                                        href={route(
                                                                            'assignments.end.form',
                                                                            assignment.id,
                                                                        )}
                                                                    >
                                                                        <XCircle className="mr-2 h-4 w-4" />
                                                                        Akhiri
                                                                    </Link>
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </TableCell>
                                                ) : (
                                                    <TableCell className="py-2" />
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
