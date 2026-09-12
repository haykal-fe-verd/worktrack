import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EditAssignmentInfo {
    id: number;
    employee_nama: string;
    tanggal_mulai: string;
    tanggal_selesai: string | null;
    tarif_jual: string | null;
    tarif_bayar: string | null;
}

export default function Edit({
    assignment,
}: PageProps<{ assignment: EditAssignmentInfo }>) {
    const { data, setData, put, processing, errors } = useForm({
        tanggal_mulai: assignment.tanggal_mulai,
        tanggal_selesai: assignment.tanggal_selesai ?? '',
        tarif_jual: assignment.tarif_jual ?? '',
        tarif_bayar: assignment.tarif_bayar ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('assignments.update', assignment.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Edit Penugasan: {assignment.employee_nama}
                </h2>
            }
        >
            <Head title={`Edit Penugasan - ${assignment.employee_nama}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
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
