import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, RoleName } from '@/types';
import { Head, useForm } from '@inertiajs/react';
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

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit User: {user.name}
                </h2>
            }
        >
            <Head title={`Edit ${user.name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <p className="mb-4 text-sm text-gray-500">
                            {user.email}
                        </p>

                        <form onSubmit={submit}>
                            <div>
                                <InputLabel htmlFor="role" value="Role" />
                                <select
                                    id="role"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.role}
                                    onChange={(e) =>
                                        setData(
                                            'role',
                                            e.target.value as RoleName,
                                        )
                                    }
                                >
                                    <option value="viewer">Viewer</option>
                                    <option value="staff_input">
                                        Staff Input
                                    </option>
                                    <option value="admin">Admin</option>
                                </select>
                                <InputError
                                    message={errors.role}
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
