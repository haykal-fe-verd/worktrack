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
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
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

    const close = () => router.visit(route('jobs.show', job.id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Periode PR Baru - ${job.nama_pekerjaan}`} />

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
                                Periode PR Baru: {job.nama_pekerjaan}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="mt-4 space-y-4">
                            <div>
                                <Label htmlFor="jenis_dokumen">
                                    Jenis Dokumen
                                </Label>
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
                                {errors.jenis_dokumen && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.jenis_dokumen}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="no_dokumen">
                                    No. Dokumen
                                </Label>
                                <Input
                                    id="no_dokumen"
                                    className="mt-1"
                                    value={data.no_dokumen}
                                    onChange={(e) =>
                                        setData(
                                            'no_dokumen',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    autoFocus
                                />
                                {errors.no_dokumen && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.no_dokumen}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="kode_po">
                                    Kode PO (opsional)
                                </Label>
                                <Input
                                    id="kode_po"
                                    className="mt-1"
                                    value={data.kode_po}
                                    onChange={(e) =>
                                        setData('kode_po', e.target.value)
                                    }
                                />
                                {errors.kode_po && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.kode_po}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="nilai_po">Nilai PO</Label>
                                <Input
                                    id="nilai_po"
                                    type="number"
                                    step="0.01"
                                    className="mt-1"
                                    value={data.nilai_po}
                                    onChange={(e) =>
                                        setData('nilai_po', e.target.value)
                                    }
                                    required
                                />
                                {errors.nilai_po && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.nilai_po}
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
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
                                        Tanggal Selesai
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
                            </div>

                            <div>
                                <Label htmlFor="jumlah_tk_rencana">
                                    Jumlah TK Rencana
                                </Label>
                                <Input
                                    id="jumlah_tk_rencana"
                                    type="number"
                                    className="mt-1"
                                    value={data.jumlah_tk_rencana}
                                    onChange={(e) =>
                                        setData(
                                            'jumlah_tk_rencana',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {errors.jumlah_tk_rencana && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.jumlah_tk_rencana}
                                    </p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="keterangan">
                                    Keterangan (opsional)
                                </Label>
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
                                {errors.keterangan && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {errors.keterangan}
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
