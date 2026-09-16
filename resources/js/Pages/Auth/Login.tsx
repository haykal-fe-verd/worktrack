import ApplicationLogo from '@/Components/ApplicationLogo';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Toaster } from '@/Components/ui/sonner';
import useFlashToast from '@/hooks/use-flash-toast';
import { useTheme } from '@/hooks/use-theme';
import { cn } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ClipboardCheck,
    Eye,
    EyeOff,
    Lock,
    LogIn,
    Mail,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const FEATURES = [
    {
        icon: ShieldCheck,
        title: 'Data Terenkripsi',
        description:
            'NIK dan data rekening karyawan dienkripsi sesuai standar keamanan.',
    },
    {
        icon: Users,
        title: 'Kelola Job & Penugasan',
        description:
            'Atur job, periode PR, dan penugasan karyawan dalam satu tempat.',
    },
    {
        icon: ClipboardCheck,
        title: 'Absensi Real-time',
        description:
            'Catat dan pantau kehadiran karyawan mingguan dengan mudah.',
    },
];

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    useFlashToast();
    const { theme } = useTheme();
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <div className="flex min-h-screen">
            <Toaster richColors position="top-right" theme={theme} />
            <Head title="Masuk" />

            <div className="relative hidden overflow-hidden bg-gradient-to-br from-pln-navy via-pln-blue-dark to-pln-blue lg:flex lg:w-1/2 lg:flex-col lg:justify-between lg:p-12">
                <div className="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-white/10 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-pln-yellow/10 blur-3xl" />

                <div className="relative flex items-center gap-3">
                    <ApplicationLogo className="h-11 w-11" />
                    <span className="text-xl font-bold text-white">
                        WorkTrack
                    </span>
                </div>

                <div className="relative space-y-8">
                    <div>
                        <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-white/90">
                            <span className="h-1.5 w-1.5 rounded-full bg-green-400" />
                            Sistem Aktif & Berjalan
                        </span>

                        <h1 className="mt-4 text-4xl font-bold text-white">
                            Kelola Tenaga Kerja
                            <br />
                            Non-Rutin PLN
                        </h1>
                        <p className="mt-4 max-w-md text-white/70">
                            Platform terintegrasi untuk mengelola karyawan,
                            job, penugasan, dan absensi secara efisien dan
                            aman.
                        </p>
                    </div>

                    <div className="space-y-4">
                        {FEATURES.map((feature) => (
                            <div
                                key={feature.title}
                                className="flex items-start gap-3 rounded-xl bg-white/10 p-4 backdrop-blur-sm"
                            >
                                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/10">
                                    <feature.icon className="h-5 w-5 text-white" />
                                </div>
                                <div>
                                    <h3 className="text-sm font-semibold text-white">
                                        {feature.title}
                                    </h3>
                                    <p className="mt-0.5 text-sm text-white/70">
                                        {feature.description}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <p className="relative text-xs text-white/50">
                    &copy; {new Date().getFullYear()} WorkTrack — PLN
                </p>
            </div>

            <div className="flex w-full flex-col items-center justify-center bg-white px-6 py-12 lg:w-1/2">
                <div className="mb-8 flex items-center gap-3 lg:hidden">
                    <ApplicationLogo className="h-11 w-11" />
                    <span className="text-xl font-bold text-pln-navy">
                        WorkTrack
                    </span>
                </div>

                <div className="w-full max-w-sm">
                    <h2 className="text-2xl font-bold text-pln-navy">
                        Masuk ke Akun Anda
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                        Masuk untuk melanjutkan ke dashboard WorkTrack
                    </p>

                    {status && (
                        <div className="mt-4 text-sm font-medium text-green-600">
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="mt-6">
                        <div>
                            <Label htmlFor="email">Email</Label>
                            <div className="relative mt-1">
                                <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className="pl-10"
                                    autoComplete="username"
                                    autoFocus
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                />
                            </div>

                            {errors.email && (
                                <p className="mt-2 text-sm text-destructive">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div className="mt-4">
                            <Label htmlFor="password">Password</Label>
                            <div className="relative mt-1">
                                <Lock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <Input
                                    id="password"
                                    type={
                                        showPassword ? 'text' : 'password'
                                    }
                                    name="password"
                                    value={data.password}
                                    className="pl-10 pr-10"
                                    autoComplete="current-password"
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowPassword((prev) => !prev)
                                    }
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                >
                                    {showPassword ? (
                                        <EyeOff className="h-4 w-4" />
                                    ) : (
                                        <Eye className="h-4 w-4" />
                                    )}
                                    <span className="sr-only">
                                        {showPassword
                                            ? 'Sembunyikan password'
                                            : 'Tampilkan password'}
                                    </span>
                                </button>
                            </div>

                            {errors.password && (
                                <p className="mt-2 text-sm text-destructive">
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <div className="mt-4 flex items-center justify-between">
                            <label className="flex items-center gap-2">
                                <Checkbox
                                    name="remember"
                                    checked={data.remember}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'remember',
                                            checked === true,
                                        )
                                    }
                                />
                                <span className="text-sm text-slate-600">
                                    Ingat saya
                                </span>
                            </label>

                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="rounded-md text-sm text-pln-blue hover:text-pln-blue-dark focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                >
                                    Lupa password?
                                </Link>
                            )}
                        </div>

                        <Button
                            className={cn(
                                'mt-6 w-full bg-pln-navy hover:bg-pln-navy/90',
                            )}
                            disabled={processing}
                        >
                            <LogIn className="mr-2 h-4 w-4" />
                            Masuk
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    );
}
