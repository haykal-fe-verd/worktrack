import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        nama: '',
        nik: '',
        alamat: '',
        no_rekening: '',
        nama_bank: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('employees.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Tambah Karyawan
                </h2>
            }
        >
            <Head title="Tambah Karyawan" />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel htmlFor="nama" value="Nama" />
                                <TextInput
                                    id="nama"
                                    className="mt-1 block w-full"
                                    value={data.nama}
                                    onChange={(e) =>
                                        setData('nama', e.target.value)
                                    }
                                    required
                                    isFocused
                                />
                                <InputError
                                    message={errors.nama}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel htmlFor="nik" value="NIK" />
                                <TextInput
                                    id="nik"
                                    className="mt-1 block w-full"
                                    value={data.nik}
                                    onChange={(e) =>
                                        setData('nik', e.target.value)
                                    }
                                    maxLength={16}
                                    required
                                />
                                <InputError
                                    message={errors.nik}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="alamat"
                                    value="Alamat"
                                />
                                <textarea
                                    id="alamat"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.alamat}
                                    onChange={(e) =>
                                        setData('alamat', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.alamat}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="no_rekening"
                                    value="No. Rekening"
                                />
                                <TextInput
                                    id="no_rekening"
                                    className="mt-1 block w-full"
                                    value={data.no_rekening}
                                    onChange={(e) =>
                                        setData(
                                            'no_rekening',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.no_rekening}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="nama_bank"
                                    value="Nama Bank (opsional)"
                                />
                                <TextInput
                                    id="nama_bank"
                                    className="mt-1 block w-full"
                                    value={data.nama_bank}
                                    onChange={(e) =>
                                        setData(
                                            'nama_bank',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.nama_bank}
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
