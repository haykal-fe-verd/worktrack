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
import { JobDetail, JobPeriodRow, PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';

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

    const close = () => router.visit(route('jobs.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={job.nama_pekerjaan} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent
                    className="sm:max-w-3xl"
                    aria-describedby={undefined}
                >
                    <DialogHeader>
                        <DialogTitle>{job.nama_pekerjaan}</DialogTitle>
                    </DialogHeader>

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
                        <div className="flex gap-2">
                            {hasActivePeriod && (
                                <Button asChild>
                                    <Link href={route('jobs.renew', job.id)}>
                                        Perbarui PR
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" onClick={toggleStatus}>
                                {job.status === 'aktif'
                                    ? 'Tandai Selesai'
                                    : 'Tandai Aktif'}
                            </Button>
                        </div>
                    )}

                    <div>
                        <h3 className="mb-2 text-sm font-semibold text-pln-navy">
                            Riwayat Periode PR
                        </h3>

                        <div className="max-h-80 overflow-y-auto overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">
                                            #
                                        </TableHead>
                                        <TableHead>Jenis</TableHead>
                                        <TableHead>No. Dokumen</TableHead>
                                        <TableHead>Periode</TableHead>
                                        <TableHead>Nilai PO</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {periods.map((period, index) => (
                                        <TableRow key={period.id}>
                                            <TableCell className="text-slate-500">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell>
                                                {period.jenis_dokumen}
                                            </TableCell>
                                            <TableCell>
                                                {period.no_dokumen}
                                            </TableCell>
                                            <TableCell>
                                                {period.tanggal_mulai}
                                                {period.tanggal_selesai
                                                    ? ` s/d ${period.tanggal_selesai}`
                                                    : ''}
                                            </TableCell>
                                            <TableCell>
                                                {period.nilai_po.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {period.status}
                                            </TableCell>
                                            <TableCell>
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
                                                                    'job-periods.show',
                                                                    period.id,
                                                                )}
                                                            >
                                                                Detail
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
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
