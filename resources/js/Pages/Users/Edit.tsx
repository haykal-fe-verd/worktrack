import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, RoleName } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface EditableUser {
    id: number;
    name: string;
    email: string;
    role: RoleName | null;
}

export default function Edit({ user }: PageProps<{ user: EditableUser }>) {
    const { data, setData, put, processing, errors } = useForm({
        role: user.role ?? 'viewer',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('users.update', user.id));
    };

    const close = () => router.visit(route('users.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Manajemen User
                </h2>
            }
        >
            <Head title={`Edit ${user.name}`} />

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
                                Edit User: {user.name}
                            </DialogTitle>
                        </DialogHeader>

                        <p className="mt-1 text-sm text-gray-500">
                            {user.email}
                        </p>

                        <div className="mt-4">
                            <Label htmlFor="role">Role</Label>
                            <Select
                                value={data.role}
                                onValueChange={(value) =>
                                    setData('role', value as RoleName)
                                }
                            >
                                <SelectTrigger id="role" className="mt-1">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="viewer">
                                        Viewer
                                    </SelectItem>
                                    <SelectItem value="staff_input">
                                        Staff Input
                                    </SelectItem>
                                    <SelectItem value="admin">
                                        Admin
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            {errors.role && (
                                <p className="mt-2 text-sm text-destructive">
                                    {errors.role}
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
                                Simpan
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
