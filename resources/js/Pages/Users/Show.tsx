import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, UserDetail } from '@/types';
import { Head, router } from '@inertiajs/react';

const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    staff_input: 'Staff Input',
    viewer: 'Viewer',
};

export default function Show({ user }: PageProps<{ user: UserDetail }>) {
    const close = () => router.visit(route('users.index'));

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Manajemen User
                </h2>
            }
        >
            <Head title={user.name} />

            <Dialog
                open
                onOpenChange={(open) => {
                    if (!open) {
                        close();
                    }
                }}
            >
                <DialogContent aria-describedby={undefined}>
                    <DialogHeader>
                        <DialogTitle>{user.name}</DialogTitle>
                    </DialogHeader>

                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-slate-500">Email</dt>
                            <dd className="font-medium">{user.email}</dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Role</dt>
                            <dd className="font-medium">
                                {user.role
                                    ? (ROLE_LABELS[user.role] ?? user.role)
                                    : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-slate-500">Terdaftar Sejak</dt>
                            <dd className="font-medium">
                                {user.created_at}
                            </dd>
                        </div>
                    </dl>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
