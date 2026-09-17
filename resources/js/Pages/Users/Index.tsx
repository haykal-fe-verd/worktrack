import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import Pagination from '@/Components/Pagination';
import { Badge } from '@/Components/ui/badge';
import { Button, buttonVariants } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { cn } from '@/lib/utils';
import { Paginated, PageProps, UserRow } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    Download,
    Eye,
    KeyRound,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Filters {
    search?: string;
    role?: string;
}

const ROLE_LABELS: Record<string, string> = {
    admin: 'Admin',
    staff_input: 'Staff Input',
    viewer: 'Viewer',
};

const ROLE_BADGE_CLASSES: Record<string, string> = {
    admin: 'border-transparent bg-purple-100 text-purple-700 hover:bg-purple-100',
    staff_input:
        'border-transparent bg-blue-100 text-blue-700 hover:bg-blue-100',
    viewer:
        'border-transparent bg-slate-100 text-slate-600 hover:bg-slate-100',
};

export default function Index({
    users,
    filters,
    perPageOptions,
}: PageProps<{
    users: Paginated<UserRow>;
    filters: Filters;
    perPageOptions: number[];
}>) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole] = useState(filters.role ?? '');
    const [deletingUser, setDeletingUser] = useState<UserRow | null>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deletingId, setDeletingId] = useState<number | null>(null);

    const applyFilters: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(
            route('users.index'),
            { search, role, per_page: users.per_page },
            { preserveState: true, replace: true },
        );
    };

    const changePerPage = (value: string) => {
        router.get(
            route('users.index'),
            { search, role, per_page: value },
            { preserveState: true, replace: true },
        );
    };

    const deleteUser = (userId: number) => {
        if (deletingId !== null) {
            return;
        }

        setDeletingId(userId);
        router.delete(route('users.destroy', userId), {
            onFinish: () => {
                setDeletingId(null);
                setDeleteDialogOpen(false);
            },
        });
    };

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
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <form
                            onSubmit={applyFilters}
                            className="mb-4 flex flex-wrap items-end gap-3"
                        >
                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Cari nama/email
                                </label>
                                <Input
                                    type="text"
                                    value={search}
                                    onChange={(e) =>
                                        setSearch(e.target.value)
                                    }
                                    className="mt-1"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Role
                                </label>
                                <Select
                                    value={role === '' ? 'semua' : role}
                                    onValueChange={(value) =>
                                        setRole(
                                            value === 'semua' ? '' : value,
                                        )
                                    }
                                >
                                    <SelectTrigger className="mt-1 w-[160px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="semua">
                                            Semua
                                        </SelectItem>
                                        <SelectItem value="admin">
                                            Admin
                                        </SelectItem>
                                        <SelectItem value="staff_input">
                                            Staff Input
                                        </SelectItem>
                                        <SelectItem value="viewer">
                                            Viewer
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-slate-500">
                                    Per Halaman
                                </label>
                                <Select
                                    value={String(users.per_page)}
                                    onValueChange={changePerPage}
                                >
                                    <SelectTrigger className="mt-1 w-[100px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {perPageOptions.map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={String(option)}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button type="submit">
                                <Search className="mr-2 h-4 w-4" />
                                Terapkan
                            </Button>

                            <div className="flex flex-wrap gap-2 sm:ml-auto">
                                <Button variant="outline" asChild>
                                    <a href={route('users.export')}>
                                        <Download className="mr-2 h-4 w-4" />
                                        Export Excel
                                    </a>
                                </Button>
                                <Button asChild>
                                    <Link href={route('users.create')}>
                                        <Plus className="mr-2 h-4 w-4" />
                                        Tambah User
                                    </Link>
                                </Button>
                            </div>
                        </form>

                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 py-2">
                                            #
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Nama
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Email
                                        </TableHead>
                                        <TableHead className="py-2">
                                            Role
                                        </TableHead>
                                        <TableHead className="py-2" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {users.data.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="py-2 text-center text-slate-500"
                                            >
                                                Tidak ada data user.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {users.data.map((user, index) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="py-2 text-slate-500">
                                                {(users.from ?? 1) + index}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {user.name}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {user.email}
                                            </TableCell>
                                            <TableCell className="py-2">
                                                {user.role ? (
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            ROLE_BADGE_CLASSES[
                                                                user.role
                                                            ] ??
                                                            'border-transparent bg-slate-100 text-slate-600 hover:bg-slate-100'
                                                        }
                                                    >
                                                        {ROLE_LABELS[
                                                            user.role
                                                        ] ?? user.role}
                                                    </Badge>
                                                ) : (
                                                    '—'
                                                )}
                                            </TableCell>
                                            <TableCell className="py-2 text-right">
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
                                                                    'users.show',
                                                                    user.id,
                                                                )}
                                                            >
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                Detail
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={route(
                                                                    'users.edit',
                                                                    user.id,
                                                                )}
                                                            >
                                                                <Pencil className="mr-2 h-4 w-4" />
                                                                Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            asChild
                                                        >
                                                            <Link
                                                                href={route(
                                                                    'users.reset-password.edit',
                                                                    user.id,
                                                                )}
                                                            >
                                                                <KeyRound className="mr-2 h-4 w-4" />
                                                                Reset Password
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            className="text-destructive focus:text-destructive"
                                                            disabled={
                                                                deletingId ===
                                                                user.id
                                                            }
                                                            onSelect={() => {
                                                                setDeletingUser(
                                                                    user,
                                                                );
                                                                setDeleteDialogOpen(
                                                                    true,
                                                                );
                                                            }}
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                            Hapus
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

            <AlertDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus user?</AlertDialogTitle>
                        <AlertDialogDescription>
                            {`Akun ${deletingUser?.name} akan dihapus permanen dan tidak bisa dikembalikan.`}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            disabled={deletingId !== null}
                            className={cn(
                                buttonVariants({ variant: 'destructive' }),
                            )}
                            onClick={() =>
                                deletingUser && deleteUser(deletingUser.id)
                            }
                        >
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AuthenticatedLayout>
    );
}
