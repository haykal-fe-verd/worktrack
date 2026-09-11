import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface ImportResult {
    created: number;
    skipped: number;
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
        post(route('employees.import.store'), {
            forceFormData: true,
            onSuccess: () => reset('file'),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Import Data Karyawan
                </h2>
            }
        >
            <Head title="Import Data Karyawan" />

            <div className="py-12">
                <div className="mx-auto max-w-xl space-y-6 sm:px-6 lg:px-8">
                    {result && (
                        <div className="rounded-lg bg-white p-6 shadow-sm">
                            <h3 className="text-sm font-semibold text-pln-navy">
                                Hasil Import Terakhir
                            </h3>
                            <dl className="mt-3 space-y-1 text-sm text-slate-600">
                                <div className="flex justify-between">
                                    <dt>Berhasil</dt>
                                    <dd>{result.created}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Dilewati (sudah ada)</dt>
                                    <dd>{result.skipped}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Gagal</dt>
                                    <dd>{result.errorCount}</dd>
                                </div>
                            </dl>

                            {result.errorCount > 0 && (
                                <a
                                    href={route('employees.import.errors')}
                                    className="mt-4 inline-block rounded-md border border-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-pln-blue hover:bg-pln-blue/10"
                                >
                                    Download Laporan Error
                                </a>
                            )}
                        </div>
                    )}

                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <p className="mb-4 text-sm text-slate-600">
                            Upload file Excel (.xlsx/.xls) dengan kolom
                            NAMA, NIK, ALAMAT, NO REKENING.
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
                                <p className="mt-2 text-sm text-red-600">
                                    {errors.file}
                                </p>
                            )}

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton
                                    disabled={processing || !data.file}
                                >
                                    Upload
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
