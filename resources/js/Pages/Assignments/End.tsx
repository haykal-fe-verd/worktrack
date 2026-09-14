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

interface EndAssignmentInfo {
    id: number;
    job_period_id: number;
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

    const close = () =>
        router.visit(route('job-periods.show', assignment.job_period_id));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Data Job & Periode PR
                </h2>
            }
        >
            <Head title={`Akhiri Penugasan - ${assignment.employee_nama}`} />

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
                                Akhiri Penugasan: {assignment.employee_nama}
                            </DialogTitle>
                        </DialogHeader>

                        <div className="mt-4">
                            <Label htmlFor="tanggal_selesai">
                                Tanggal Akhir
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
                                min={assignment.tanggal_mulai}
                                required
                                autoFocus
                            />
                            {errors.tanggal_selesai && (
                                <p className="mt-2 text-sm text-destructive">
                                    {errors.tanggal_selesai}
                                </p>
                            )}
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
                                Akhiri Penugasan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
