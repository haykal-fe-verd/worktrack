import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface RenewJob {
    id: number;
    nama_pekerjaan: string;
}

export default function Create({ job }: PageProps<{ job: RenewJob }>) {
    const { data, setData, post, processing, errors } = useForm({
        jenis_dokumen: 'PR',
        no_dokumen: '',
        kode_po: '',
        nilai_po: '',
        tanggal_mulai: '',
        tanggal_selesai: '',
        jumlah_tk_rencana: '',
        keterangan: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('jobs.periods.store', job.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Periode PR Baru: {job.nama_pekerjaan}
                </h2>
            }
        >
            <Head title={`Periode PR Baru - ${job.nama_pekerjaan}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form onSubmit={submit}>
                            <div>
                                <InputLabel
                                    htmlFor="jenis_dokumen"
                                    value="Jenis Dokumen"
                                />
                                <select
                                    id="jenis_dokumen"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.jenis_dokumen}
                                    onChange={(e) =>
                                        setData(
                                            'jenis_dokumen',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="PR">PR</option>
                                    <option value="PO">PO</option>
                                    <option value="DO">DO</option>
                                    <option value="WO">WO</option>
                                </select>
                                <InputError
                                    message={errors.jenis_dokumen}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="no_dokumen"
                                    value="No. Dokumen"
                                />
                                <TextInput
                                    id="no_dokumen"
                                    className="mt-1 block w-full"
                                    value={data.no_dokumen}
                                    onChange={(e) =>
                                        setData(
                                            'no_dokumen',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    isFocused
                                />
                                <InputError
                                    message={errors.no_dokumen}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="kode_po"
                                    value="Kode PO (opsional)"
                                />
                                <TextInput
                                    id="kode_po"
                                    className="mt-1 block w-full"
                                    value={data.kode_po}
                                    onChange={(e) =>
                                        setData('kode_po', e.target.value)
                                    }
                                />
                                <InputError
                                    message={errors.kode_po}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="nilai_po"
                                    value="Nilai PO"
                                />
                                <TextInput
                                    id="nilai_po"
                                    type="number"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.nilai_po}
                                    onChange={(e) =>
                                        setData('nilai_po', e.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.nilai_po}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4 grid grid-cols-2 gap-3">
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

                                <div>
                                    <InputLabel
                                        htmlFor="tanggal_selesai"
                                        value="Tanggal Selesai"
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
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="jumlah_tk_rencana"
                                    value="Jumlah TK Rencana"
                                />
                                <TextInput
                                    id="jumlah_tk_rencana"
                                    type="number"
                                    className="mt-1 block w-full"
                                    value={data.jumlah_tk_rencana}
                                    onChange={(e) =>
                                        setData(
                                            'jumlah_tk_rencana',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.jumlah_tk_rencana}
                                    className="mt-2"
                                />
                            </div>

                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="keterangan"
                                    value="Keterangan (opsional)"
                                />
                                <textarea
                                    id="keterangan"
                                    className="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-pln-blue focus:ring-pln-blue"
                                    value={data.keterangan}
                                    onChange={(e) =>
                                        setData(
                                            'keterangan',
                                            e.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={errors.keterangan}
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
