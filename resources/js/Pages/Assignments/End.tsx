import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EndAssignmentInfo {
    id: number;
    employee_nama: string;
    tanggal_mulai: string;
}

export default function End({
    assignment,
}: PageProps<{ assignment: EndAssignmentInfo }>) {
    const { data, setData, patch, processing, errors } = useForm({
        tanggal_selesai: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('assignments.end', assignment.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Akhiri Penugasan: {assignment.employee_nama}
                </h2>
            }
        >
            <Head title={`Akhiri Penugasan - ${assignment.employee_nama}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel
                                    htmlFor="tanggal_selesai"
                                    value="Tanggal Akhir"
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
                                    min={assignment.tanggal_mulai}
                                    required
                                    isFocused
                                />
                                <InputError
                                    message={errors.tanggal_selesai}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-6 flex justify-end">
                                <PrimaryButton disabled={processing}>
                                    Akhiri Penugasan
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
