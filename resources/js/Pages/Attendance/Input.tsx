import { Button } from '@/Components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
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
import {
    AttendanceCellData,
    AttendanceGridRow,
    AttendanceStatusValue,
    JobOption,
    PageProps,
} from '@/types';
import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

const STATUS_LABEL: Record<AttendanceStatusValue, string> = {
    hadir: 'H',
    tidak_hadir: 'A',
    izin: 'I',
};

const STATUS_FULL_LABEL: Record<AttendanceStatusValue, string> = {
    hadir: 'Hadir',
    tidak_hadir: 'Tidak Hadir',
    izin: 'Izin',
};

function AttendanceCellButton({
    assignmentId,
    cell,
}: {
    assignmentId: number;
    cell: AttendanceCellData;
}) {
    const [open, setOpen] = useState(false);
    const [catatan, setCatatan] = useState(cell.catatan ?? '');
    const [processing, setProcessing] = useState(false);

    const save = (status: AttendanceStatusValue) => {
        setProcessing(true);
        router.post(
            route('attendance.input.store'),
            {
                assignment_id: assignmentId,
                tanggal: cell.tanggal,
                status,
                catatan: catatan || null,
            },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['rows', 'flash', 'errors'],
                onSuccess: () => {
                    setOpen(false);
                },
                onError: () => {
                    // Keep the popover open so the user can see the
                    // validation error (surfaced via the flash/errors
                    // props) and retry instead of losing their input.
                },
                onFinish: () => {
                    setProcessing(false);
                },
            },
        );
    };

    if (cell.disabled) {
        return (
            <div className="flex h-9 w-9 items-center justify-center rounded-md bg-slate-100 text-xs text-slate-300">
                {cell.status ? STATUS_LABEL[cell.status] : ''}
            </div>
        );
    }

    const colorClass =
        cell.status === 'hadir'
            ? 'border-green-200 bg-green-50 text-green-700'
            : cell.status === 'tidak_hadir'
              ? 'border-red-200 bg-red-50 text-red-700'
              : cell.status === 'izin'
                ? 'border-pln-yellow/40 bg-pln-yellow/10 text-pln-navy'
                : 'border-slate-200 bg-white text-slate-400 hover:border-pln-blue';

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className={`flex h-9 w-9 items-center justify-center rounded-md border text-xs font-semibold ${colorClass}`}
                >
                    {cell.status ? STATUS_LABEL[cell.status] : '-'}
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-64">
                <div className="space-y-3">
                    <p className="text-xs font-medium text-slate-500">
                        {cell.tanggal}
                    </p>
                    <div className="flex gap-2">
                        {(
                            Object.keys(
                                STATUS_FULL_LABEL,
                            ) as AttendanceStatusValue[]
                        ).map((status) => (
                            <Button
                                key={status}
                                type="button"
                                size="sm"
                                variant={
                                    cell.status === status
                                        ? 'default'
                                        : 'outline'
                                }
                                disabled={processing}
                                onClick={() => save(status)}
                            >
                                {STATUS_FULL_LABEL[status]}
                            </Button>
                        ))}
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-slate-500">
                            Catatan (opsional)
                        </label>
                        <textarea
                            className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                            rows={2}
                            value={catatan}
                            onChange={(e) => setCatatan(e.target.value)}
                        />
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}

export default function Input({
    jobs,
    selectedJobId,
    weekStart,
    weekDates,
    hasActivePeriod,
    rows,
}: PageProps<{
    jobs: JobOption[];
    selectedJobId: number | null;
    weekStart: string;
    weekDates: string[];
    hasActivePeriod: boolean;
    rows: AttendanceGridRow[];
}>) {
    const changeJob = (value: string) => {
        router.get(
            route('attendance.input'),
            { job_id: value, week_start: weekStart },
            { preserveState: true },
        );
    };

    const changeWeek = (direction: -1 | 1) => {
        const newStart = new Date(weekStart);
        newStart.setDate(newStart.getDate() + direction * 7);
        router.get(
            route('attendance.input'),
            {
                job_id: selectedJobId ?? undefined,
                week_start: newStart.toISOString().slice(0, 10),
            },
            { preserveState: true },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Input Absensi
                </h2>
            }
        >
            <Head title="Input Absensi" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <div className="mb-6 flex flex-wrap items-end gap-4">
                            <div className="w-64">
                                <label className="block text-xs font-medium text-slate-500">
                                    Job
                                </label>
                                <Select
                                    value={
                                        selectedJobId
                                            ? String(selectedJobId)
                                            : undefined
                                    }
                                    onValueChange={changeJob}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue placeholder="Pilih Job" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {jobs.map((job) => (
                                            <SelectItem
                                                key={job.id}
                                                value={String(job.id)}
                                            >
                                                {job.nama_pekerjaan}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {selectedJobId && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => changeWeek(-1)}
                                    >
                                        <ChevronLeft className="h-4 w-4" />
                                    </Button>
                                    <span className="text-sm text-slate-600">
                                        {weekDates[0]} – {weekDates[6]}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => changeWeek(1)}
                                    >
                                        <ChevronRight className="h-4 w-4" />
                                    </Button>
                                </div>
                            )}
                        </div>

                        {!selectedJobId && (
                            <p className="text-sm text-slate-500">
                                Pilih Job untuk mulai mengisi absensi.
                            </p>
                        )}

                        {selectedJobId && !hasActivePeriod && (
                            <div className="rounded-lg bg-pln-yellow/20 p-4 text-sm text-pln-navy">
                                Job ini tidak punya Periode PR yang
                                aktif — tidak ada penugasan yang bisa
                                diisi absensinya.
                            </div>
                        )}

                        {selectedJobId && hasActivePeriod && (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-12 py-2">
                                                #
                                            </TableHead>
                                            <TableHead className="py-2">
                                                Karyawan
                                            </TableHead>
                                            {weekDates.map((date) => (
                                                <TableHead
                                                    key={date}
                                                    className="py-2 text-center"
                                                >
                                                    {date.slice(5)}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {rows.map((row, index) => (
                                            <TableRow
                                                key={row.assignment_id}
                                            >
                                                <TableCell className="py-2 text-slate-500">
                                                    {index + 1}
                                                </TableCell>
                                                <TableCell className="py-2">
                                                    {row.employee_nama}
                                                </TableCell>
                                                {row.cells.map((cell) => (
                                                    <TableCell
                                                        key={
                                                            cell.tanggal
                                                        }
                                                        className="py-2 text-center"
                                                    >
                                                        <AttendanceCellButton
                                                            assignmentId={
                                                                row.assignment_id
                                                            }
                                                            cell={cell}
                                                        />
                                                    </TableCell>
                                                ))}
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
