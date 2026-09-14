import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, RoleName } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: RoleName | null;
}

export default function Index({ users }: PageProps<{ users: UserRow[] }>) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Manajemen User
                </h2>
            }
        >
            <Head title="Manajemen User" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <div className="mb-4 flex justify-end">
                            <Button asChild>
                                <Link href={route('users.create')}>
                                    Tambah User
                                </Link>
                            </Button>
                        </div>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell>
                                                {user.name}
                                            </TableCell>
                                            <TableCell>
                                                {user.email}
                                            </TableCell>
                                            <TableCell>
                                                {user.role ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Link
                                                    href={route(
                                                        'users.edit',
                                                        user.id,
                                                    )}
                                                    className="text-pln-blue hover:text-pln-blue-dark"
                                                >
                                                    Edit
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
