import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { EmployeeDetail, PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Edit({
    employee,
}: PageProps<{ employee: EmployeeDetail }>) {
    const { data, setData, put, processing, errors } = useForm({
        nama: employee.nama,
        nik: employee.nik,
        alamat: employee.alamat,
        no_rekening: employee.no_rekening,
        nama_bank: employee.nama_bank ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('employees.update', employee.id));
    };

    const close = () => router.visit(route('employees.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Karyawan
                </h2>
            }
        >
            <Head title={`Edit ${employee.nama}`} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent aria-describedby={undefined}>
                    <form onSubmit={submit}>
                        <DialogHeader>
                            <DialogTitle>
                                Edit Karyawan: {employee.nama}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="nama">Nama</Label>
                                <Input
                                    id="nama"
                                    className="mt-1"
                                    value={data.nama}
                                    onChange={(e) =>
                                        setData('nama', e.target.value)
                                    }
                                    required
                                    autoFocus
                                />
                                {errors.nama && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nama}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="nik">NIK</Label>
                                <Input
                                    id="nik"
                                    className="mt-1"
                                    value={data.nik}
                                    onChange={(e) =>
                                        setData('nik', e.target.value)
                                    }
                                    maxLength={16}
                                    required
                                />
                                {errors.nik && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nik}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="alamat">Alamat</Label>
                                <textarea
                                    id="alamat"
                                    className="mt-1 block w-full rounded-md border-slate-300 bg-white text-pln-navy shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.alamat}
                                    onChange={(e) =>
                                        setData('alamat', e.target.value)
                                    }
                                    required
                                />
                                {errors.alamat && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.alamat}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="no_rekening">
                                    No. Rekening
                                </Label>
                                <Input
                                    id="no_rekening"
                                    className="mt-1"
                                    value={data.no_rekening}
                                    onChange={(e) =>
                                        setData(
                                            'no_rekening',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.no_rekening && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.no_rekening}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="nama_bank">
                                    Nama Bank (opsional)
                                </Label>
                                <Input
                                    id="nama_bank"
                                    className="mt-1"
                                    value={data.nama_bank}
                                    onChange={(e) =>
                                        setData('nama_bank', e.target.value)
                                    }
                                />
                                {errors.nama_bank && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nama_bank}
                                    </p>
                                )}
                            </div>
                        </div>

                        <DialogFooter className="mt-6">
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={close}
                            >
                                Batal
                            </Button>
                            <Button type="submit" disabled={processing}>
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
