import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
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
import { JobDetail, JobPeriodRow, PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    Eye,
    MoreHorizontal,
    Power,
    PowerOff,
    RefreshCw,
} from 'lucide-react';
import { useState } from 'react';

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
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [toggling, setToggling] = useState(false);

    const toggleStatus = () => {
        if (toggling) {
            return;
        }

        setToggling(true);
        router.patch(route('jobs.toggle-status', job.id), {}, {
            onFinish: () => {
                setToggling(false);
                setConfirmOpen(false);
            },
        });
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
                                <Badge
                                    variant="outline"
                                    className={
                                        job.status === 'aktif'
                                            ? 'border-transparent bg-green-100 text-green-700 hover:bg-green-100'
                                            : 'border-transparent bg-slate-100 text-slate-600 hover:bg-slate-100'
                                    }
                                >
                                    {job.status === 'aktif'
                                        ? 'Aktif'
                                        : 'Selesai'}
                                </Badge>
                            </dd>
                        </div>
                    </dl>

                    {canManage && (
                        <div className="flex gap-2">
                            {hasActivePeriod && (
                                <Button asChild>
                                    <Link href={route('jobs.renew', job.id)}>
                                        <RefreshCw className="mr-2 h-4 w-4" />
                                        Perbarui PR
                                    </Link>
                                </Button>
                            )}
                            <Button
                                variant="outline"
                                onClick={() => setConfirmOpen(true)}
                            >
                                {job.status === 'aktif' ? (
                                    <PowerOff className="mr-2 h-4 w-4" />
                                ) : (
                                    <Power className="mr-2 h-4 w-4" />
                                )}
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
                                        <TableHead className="w-12 py-2">
                                            #
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Jenis
                                        </TableHead>
                                        <TableHead className="py-2">
                                            No. Dokumen
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Periode
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Nilai PO
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Status
                                        </TableHead>
                                        <TableHead className="py-2" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {periods.map((period, index) => (
                                        <TableRow key={period.id}>
                                            <TableCell className="py-2 text-slate-500">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {period.jenis_dokumen}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {period.no_dokumen}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {period.tanggal_mulai}
                                                {period.tanggal_selesai
                                                    ? ` s/d ${period.tanggal_selesai}`
                                                    : ''}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {period.nilai_po.toLocaleString(
                                                    'id-ID',
                                                )}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                <Badge
                                                    variant="outline"
                                                    className={
                                                        PERIOD_STATUS_CLASSES[
                                                            period.status
                                                        ]
                                                    }
                                                >
                                                    {PERIOD_STATUS_LABELS[
                                                        period.status
                                                    ] ?? period.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="py-2">
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
                                                                <Eye className="mr-2 h-4 w-4" />
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

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {job.status === 'aktif'
                                ? 'Tandai job selesai?'
                                : 'Tandai job aktif?'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {job.status === 'aktif'
                                ? `Status ${job.nama_pekerjaan} akan diubah menjadi Selesai.`
                                : `Status ${job.nama_pekerjaan} akan diubah menjadi Aktif.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={toggling}
                            onClick={toggleStatus}
                        >
                            Lanjutkan
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AuthenticatedLayout>
    );
}
