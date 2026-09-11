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
                            <Link
                                href={route('users.create')}
                                className="rounded-md border border-transparent bg-pln-blue px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-pln-blue-dark"
                            >
                                Tambah User
                            </Link>
                        </div>

                        <table className="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Nama
                                    </th>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Email
                                    </th>
                                    <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">
                                        Role
                                    </th>
                                    <th className="px-3 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {users.map((user) => (
                                    <tr key={user.id}>
                                        <td className="px-3 py-2 text-sm text-gray-900">
                                            {user.name}
                                        </td>
                                        <td className="px-3 py-2 text-sm text-gray-500">
                                            {user.email}
                                        </td>
                                        <td className="px-3 py-2 text-sm text-gray-500">
                                            {user.role ?? '—'}
                                        </td>
                                        <td className="px-3 py-2 text-right text-sm">
                                            <Link
                                                href={route(
                                                    'users.edit',
                                                    user.id,
                                                )}
                                                className="text-pln-blue hover:text-pln-blue-dark"
                                            >
                                                Edit
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
