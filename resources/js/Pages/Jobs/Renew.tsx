import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

interface RenewJob {
    id: number;
    nama_pekerjaan: string;
}

export default function Renew({
    job,
    activePeriodId,
}: PageProps<{ job: RenewJob; activePeriodId: number | null }>) {
    const close = () => router.visit(route('jobs.show', job.id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Perbarui PR - ${job.nama_pekerjaan}`} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent aria-describedby={undefined}>
                    <DialogHeader>
                        <DialogTitle>
                            Perbarui PR: {job.nama_pekerjaan}
                        </DialogTitle>
                    </DialogHeader>

                    <p className="text-sm text-slate-600">
                        Pilih salah satu skenario sesuai kondisi pembaruan
                        No. PR untuk pekerjaan ini:
                    </p>

                    <div className="space-y-4">
                        <Link
                            href={route('jobs.periods.create', job.id)}
                            className="block rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-pln-blue"
                        >
                            <h3 className="text-sm font-semibold text-pln-navy">
                                (a) Lanjutan Job yang Sama
                            </h3>
                            <p className="mt-1 text-sm text-slate-600">
                                No. PR baru untuk pekerjaan yang sama —
                                periode lama otomatis ditutup dan
                                dihubungkan ke periode baru.
                            </p>
                        </Link>

                        <Link
                            href={route('jobs.create')}
                            className="block rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-pln-blue"
                        >
                            <h3 className="text-sm font-semibold text-pln-navy">
                                (b) Job Baru Sama Sekali
                            </h3>
                            <p className="mt-1 text-sm text-slate-600">
                                Pekerjaan yang benar-benar berbeda —
                                dibuat sebagai Job baru, tanpa relasi ke
                                Job ini.
                            </p>
                        </Link>

                        {activePeriodId && (
                            <Link
                                href={route(
                                    'job-periods.show',
                                    activePeriodId,
                                )}
                                className="block rounded-lg border border-slate-200 bg-white p-4 shadow-sm hover:border-pln-blue"
                            >
                                <h3 className="text-sm font-semibold text-pln-navy">
                                    (c) Assignment Saja
                                </h3>
                                <p className="mt-1 text-sm text-slate-600">
                                    Job & No. PR tidak berubah — kelola
                                    penugasan pekerja untuk periode
                                    aktif ini.
                                </p>
                            </Link>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
