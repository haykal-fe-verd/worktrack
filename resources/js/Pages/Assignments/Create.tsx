import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
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

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Assign Karyawan: {jobPeriod.no_dokumen}
                </h2>
            }
        >
            <Head title={`Assign Karyawan - ${jobPeriod.no_dokumen}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel
                                    htmlFor="employee_id"
                                    value="Karyawan"
                                />
                                <select
                                    id="employee_id"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.employee_id}
                                    onChange={(e) =>
                                        setData('employee_id', e.target.value)
                                    }
                                    required
                                >
                                    <option value="">Pilih karyawan</option>
                                    {employees.map((employee) => (
                                        <option
                                            key={employee.id}
                                            value={employee.id}
                                        >
                                            {employee.nama}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={errors.employee_id}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tanggal_mulai"
                                    value="Tanggal Mulai"
                                />
                                <TextInput
                                    id="tanggal_mulai"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.tanggal_mulai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_mulai',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.tanggal_mulai}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tanggal_selesai"
                                    value="Tanggal Selesai (opsional)"
                                />
                                <TextInput
                                    id="tanggal_selesai"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.tanggal_selesai}
                                    onChange={(e) =>
                                        setData(
                                            'tanggal_selesai',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.tanggal_selesai}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tarif_jual"
                                    value="Tarif Jual (opsional)"
                                />
                                <TextInput
                                    id="tarif_jual"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.tarif_jual}
                                    onChange={(e) =>
                                        setData('tarif_jual', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.tarif_jual}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="tarif_bayar"
                                    value="Tarif Bayar (opsional)"
                                />
                                <TextInput
                                    id="tarif_bayar"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.tarif_bayar}
                                    onChange={(e) =>
                                        setData('tarif_bayar', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.tarif_bayar}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton disabled={processing}>
                                    Simpan
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
