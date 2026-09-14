import { Button } from '@/Components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface ImportResult {
    created: number;
    errorCount: number;
}

export default function Import({
    result,
}: PageProps<{ result: ImportResult | null }>) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        file: File | null;
    }>({
        file: null,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('jobs.import.store'), {
            forceFormData: true,
            onSuccess: () => reset('file'),
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
            <Head title="Import Data Job & Periode PR" />

            <Sheet
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <SheetContent
                    className="overflow-y-auto sm:max-w-lg"
                    aria-describedby={undefined}
                >
                    <SheetHeader>
                        <SheetTitle>Import Data Job & Periode PR</SheetTitle>
                    </SheetHeader>

                    <div className="mt-6 space-y-6">
                        {result && (
                            <div className="rounded-lg border border-slate-200 p-4">
                                <h3 className="text-sm font-semibold text-pln-navy">
                                    Hasil Import Terakhir
                                </h3>
                                <dl className="mt-3 space-y-1 text-sm text-slate-600">
                                    <div className="flex justify-between">
                                        <dt>Berhasil</dt>
                                        <dd>{result.created}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt>Gagal</dt>
                                        <dd>{result.errorCount}</dd>
                                    </div>
                                </dl>

                                {result.errorCount > 0 && (
                                    <Button
                                        variant="outline"
                                        asChild
                                        className="mt-4"
                                    >
                                        <a
                                            href={route(
                                                'jobs.import.errors',
                                            )}
                                        >
                                            Download Laporan Error
                                        </a>
                                    </Button>
                                )}
                            </div>
                        )}

                        <div>
                            <p className="mb-4 text-sm text-slate-600">
                                Upload file Excel (.xlsx/.xls) dengan
                                kolom NO. DO/PR/WO, URAIAN PEKERJAAN,
                                JUMLAH TK, MULAI TANGGAL, S/D TANGGAL,
                                PO, NILAI PO, KETERANGAN. Setiap baris
                                akan dibuat sebagai Job baru.
                            </p>

                            <form onSubmit={submit}>
                                <input
                                    type="file"
                                    accept=".xlsx,.xls"
                                    onChange={(e) =>
                                        setData(
                                            'file',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                    className="block w-full text-sm text-slate-600"
                                />
                                {errors.file && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.file}
                                    </p>
                                )}

                                <div className="mt-6 flex justify-end">
                                    <Button
                                        disabled={processing || !data.file}
                                    >
                                        Upload
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </AuthenticatedLayout>
    );
}
