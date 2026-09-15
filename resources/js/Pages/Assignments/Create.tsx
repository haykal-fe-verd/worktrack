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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EmployeeOption {
    id: number;
    nama: string;
}

interface AssignJobPeriod {
    id: number;
    no_dokumen: string;
}

export default function Create({
    jobPeriod,
    employees,
}: PageProps<{ jobPeriod: AssignJobPeriod; employees: EmployeeOption[] }>) {
    const { data, setData, post, processing, errors } = useForm({
        employee_id: '',
        tanggal_mulai: '',
        tanggal_selesai: '',
        tarif_jual: '',
        tarif_bayar: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('job-periods.assignments.store', jobPeriod.id));
    };

    const close = () =>
        router.visit(route('job-periods.show', jobPeriod.id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Assign Karyawan - ${jobPeriod.no_dokumen}`} />

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
                                Assign Karyawan: {jobPeriod.no_dokumen}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="employee_id">
                                    Karyawan
                                </Label>
                                <Select
                                    value={data.employee_id}
                                    onValueChange={(value) =>
                                        setData('employee_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="employee_id"
                                        className="mt-1"
                                    >
                                        <SelectValue placeholder="Pilih karyawan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {employees.map((employee) => (
                                            <SelectItem
                                                key={employee.id}
                                                value={String(employee.id)}
                                            >
                                                {employee.nama}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.employee_id && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.employee_id}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tanggal_mulai">
                                    Tanggal Mulai
                                </Label>
                                <Input
                                    id="tanggal_mulai"
                                    type="date"
                                    className="mt-1"
                                    value={data.tanggal_mulai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_mulai',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.tanggal_mulai && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tanggal_mulai}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tanggal_selesai">
                                    Tanggal Selesai (opsional)
                                </Label>
                                <Input
                                    id="tanggal_selesai"
                                    type="date"
                                    className="mt-1"
                                    value={data.tanggal_selesai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_selesai',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.tanggal_selesai && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tanggal_selesai}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tarif_jual">
                                    Tarif Jual (opsional)
                                </Label>
                                <Input
                                    id="tarif_jual"
                                    type="number"
                                    step="0.01"
                                    className="mt-1"
                                    value={data.tarif_jual}
                                    onChange={(e) =>
                                        setData(
                                            'tarif_jual',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.tarif_jual && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tarif_jual}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="tarif_bayar">
                                    Tarif Bayar (opsional)
                                </Label>
                                <Input
                                    id="tarif_bayar"
                                    type="number"
                                    step="0.01"
                                    className="mt-1"
                                    value={data.tarif_bayar}
                                    onChange={(e) =>
                                        setData(
                                            'tarif_bayar',
                                            e.target.value,
                                        )
                                    }
                                />
                                {errors.tarif_bayar && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.tarif_bayar}
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
