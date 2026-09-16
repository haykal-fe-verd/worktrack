import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Paginated, PageProps, RoleName } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: RoleName | null;
}

export default function Index({
    users,
}: PageProps<{ users: Paginated<UserRow> }>) {
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
                                    {users.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={4}
                                                className="text-center text-slate-500"
                                            >
                                                Tidak ada data user.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {users.data.map((user) => (
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
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
                                                            <span className="sr-only">
                                                                Aksi
                                                            </span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={route(
                                                                    'users.edit',
                                                                    user.id,
                                                                )}
                                                            >
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <Pagination links={users.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
