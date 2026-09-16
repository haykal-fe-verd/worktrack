import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
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
    AttendanceFilters,
    AttendanceRecapRow,
    AttendanceRecapStatusValue,
    JobOption,
    PageProps,
    Paginated,
} from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const STATUS_LABEL: Record<AttendanceRecapStatusValue, string> = {
    hadir: 'H',
    tidak_hadir: 'A',
    izin: 'I',
    belum_diisi: '?',
};

export default function Rekap({
    jobs,
    filters,
    dateKeys,
    rows,
    canManage,
}: PageProps<{
    jobs: JobOption[];
    filters: AttendanceFilters;
    dateKeys: string[];
    rows: Paginated<AttendanceRecapRow>;
    canManage: boolean;
}>) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);
    const [jobId, setJobId] = useState(
        filters.job_id ? String(filters.job_id) : 'semua',
    );

    const applyFilters: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            route('attendance.rekap'),
            {
                date_from: dateFrom,
                date_to: dateTo,
                job_id: jobId === 'semua' ? undefined : jobId,
            },
            { preserveState: true, replace: true },
        );
    };

    const exportUrl = `${route('attendance.rekap.export')}?date_from=${dateFrom}&date_to=${dateTo}${
        jobId === 'semua' ? '' : `&job_id=${jobId}`
    }`;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Rekap Absensi Mingguan
                </h2>
            }
        >
            <Head title="Rekap Absensi Mingguan" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form
                            onSubmit={applyFilters}
                            className="mb-6 flex flex-wrap items-end gap-3"
                        >
                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Dari Tanggal
                                </label>
                                <Input
                                    type="date"
                                    className="mt-1"
                                    value={dateFrom}
                                    onChange={(e) =>
                                        setDateFrom(e.target.value)
                                    }
                                    required
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Sampai Tanggal
                                </label>
                                <Input
                                    type="date"
                                    className="mt-1"
                                    value={dateTo}
                                    onChange={(e) =>
                                        setDateTo(e.target.value)
                                    }
                                    required
                                />
                            </div>

                            <div className="w-56">
                                <label className="block text-xs font-medium text-slate-500">
                                    Job
                                </label>
                                <Select
                                    value={jobId}
                                    onValueChange={setJobId}
                                >
                                    <SelectTrigger className="mt-1">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="semua">
                                            Semua Job
                                        </SelectItem>
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

                            <Button type="submit">Terapkan</Button>

                            <div className="ml-auto flex gap-2">
                                <Button variant="outline" asChild>
                                    <a href={exportUrl}>Export</a>
                                </Button>
                                {canManage && (
                                    <Button asChild>
                                        <Link
                                            href={route(
                                                'attendance.input',
                                            )}
                                        >
                                            Input Absensi
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        </form>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">
                                            #
                                        </TableHead>
                                        <TableHead>Karyawan</TableHead>
                                        <TableHead>Job</TableHead>
                                        <TableHead>
                                            No. Dokumen
                                        </TableHead>
                                        {dateKeys.map((date) => (
                                            <TableHead
                                                key={date}
                                                className="text-center"
                                            >
                                                {date.slice(5)}
                                            </TableHead>
                                        ))}
                                        <TableHead className="text-center">
                                            H
                                        </TableHead>
                                        <TableHead className="text-center">
                                            A
                                        </TableHead>
                                        <TableHead className="text-center">
                                            I
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={
                                                    4 + dateKeys.length + 3
                                                }
                                                className="text-center text-slate-500"
                                            >
                                                Tidak ada data absensi
                                                untuk rentang/filter ini.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {rows.data.map((row, index) => (
                                        <TableRow
                                            key={row.assignment_id}
                                        >
                                            <TableCell className="text-slate-500">
                                                {(rows.from ?? 1) + index}
                                            </TableCell>
                                            <TableCell>
                                                {row.employee_nama}
                                            </TableCell>
                                            <TableCell>
                                                {
                                                    row.job_nama_pekerjaan
                                                }
                                            </TableCell>
                                            <TableCell>
                                                {row.no_dokumen}
                                            </TableCell>
                                            {dateKeys.map((date) => (
                                                <TableCell
                                                    key={date}
                                                    className="text-center"
                                                >
                                                    {
                                                        STATUS_LABEL[
                                                            row.days[
                                                                date
                                                            ]
                                                        ]
                                                    }
                                                </TableCell>
                                            ))}
                                            <TableCell className="text-center">
                                                {row.summary.hadir}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                {
                                                    row.summary
                                                        .tidak_hadir
                                                }
                                            </TableCell>
                                            <TableCell className="text-center">
                                                {row.summary.izin}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination links={rows.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
