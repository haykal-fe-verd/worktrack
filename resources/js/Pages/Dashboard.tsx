import { Head, Link } from "@inertiajs/react";
import BentoCard from "@/Components/BentoCard";
import { Button } from "@/Components/ui/button";
import { Card } from "@/Components/ui/card";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import type { PageProps } from "@/types";

interface RoleCounts {
    admin: number;
    staff_input: number;
    viewer: number;
    total: number;
}

interface AttendanceSummary {
    hadir: number;
    tidak_hadir: number;
    izin: number;
    belum_diisi: number;
}

interface UpcomingJobPeriod {
    id: number;
    job_id: number;
    job_nama_pekerjaan: string;
    no_dokumen: string;
    tanggal_selesai: string;
}

interface RecentEmployee {
    id: number;
    nama: string;
    status: string;
    created_at: string;
}

interface RecentJob {
    id: number;
    nama_pekerjaan: string;
    status: string;
    created_at: string;
}

function AttendanceBar({
    label,
    count,
    max,
    colorClass,
}: {
    label: string;
    count: number;
    max: number;
    colorClass: string;
}) {
    const width =
        max > 0 ? Math.max((count / max) * 100, count > 0 ? 4 : 0) : 0;

    return (
        <div>
            <div className="flex justify-between text-xs text-slate-500">
                <span>{label}</span>
                <span className="font-medium text-pln-navy">{count}</span>
            </div>
            <div className="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                <div
                    className={`h-full rounded-full ${colorClass}`}
                    style={{ width: `${width}%` }}
                />
            </div>
        </div>
    );
}

export default function Dashboard({
    roleCounts,
    employeeCount,
    jobCount,
    assignmentCount,
    belumDiisiCount,
    canManage,
    isAdmin,
    attendanceSummary,
    upcomingJobPeriods,
    recentEmployees,
    recentJobs,
}: PageProps<{
    roleCounts: RoleCounts;
    employeeCount: number;
    jobCount: number;
    assignmentCount: number;
    belumDiisiCount: number;
    canManage: boolean;
    isAdmin: boolean;
    attendanceSummary: AttendanceSummary;
    upcomingJobPeriods: UpcomingJobPeriod[];
    recentEmployees: RecentEmployee[];
    recentJobs: RecentJob[];
}>) {
    const attendanceMax = Math.max(
        attendanceSummary.hadir,
        attendanceSummary.tidak_hadir,
        attendanceSummary.izin,
        attendanceSummary.belum_diisi,
        1,
    );

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-pln-navy">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 sm:auto-rows-[130px]">
                        <BentoCard
                            title="Total User"
                            accent="blue"
                            className="col-span-2 row-span-2"
                        >
                            <div className="text-4xl font-bold">
                                {roleCounts.total}
                            </div>
                            <dl className="mt-4 space-y-1 text-sm text-white/90">
                                <div className="flex justify-between">
                                    <dt>Admin</dt>
                                    <dd>{roleCounts.admin}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Staff Input</dt>
                                    <dd>{roleCounts.staff_input}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Viewer</dt>
                                    <dd>{roleCounts.viewer}</dd>
                                </div>
                            </dl>
                        </BentoCard>

                        <BentoCard title="Karyawan" accent="white">
                            <div className="text-2xl font-bold text-pln-navy">
                                {employeeCount}
                            </div>
                        </BentoCard>

                        <BentoCard title="Job & PR" accent="white">
                            <div className="text-2xl font-bold text-pln-navy">
                                {jobCount}
                            </div>
                        </BentoCard>

                        <BentoCard title="Assignment" accent="yellow">
                            <div className="text-2xl font-bold text-pln-navy">
                                {assignmentCount}
                            </div>
                        </BentoCard>

                        <BentoCard title="Absensi Mingguan" accent="white">
                            <div className="text-2xl font-bold text-pln-navy">
                                {belumDiisiCount}
                            </div>
                            <p className="mt-1 text-xs text-slate-400">
                                belum diisi minggu ini
                            </p>
                        </BentoCard>
                    </div>

                    {canManage && (
                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={route("employees.create")}>
                                    Tambah Karyawan
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={route("jobs.create")}>
                                    Tambah Job
                                </Link>
                            </Button>
                            <Button asChild variant="outline">
                                <Link href={route("attendance.input")}>
                                    Input Absensi
                                </Link>
                            </Button>
                            {isAdmin && (
                                <Button asChild variant="outline">
                                    <Link href={route("users.create")}>
                                        Tambah User
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Card className="p-5">
                            <h3 className="text-sm font-semibold text-pln-navy">
                                Absensi Minggu Ini
                            </h3>
                            <div className="mt-4 space-y-3">
                                <AttendanceBar
                                    label="Hadir"
                                    count={attendanceSummary.hadir}
                                    max={attendanceMax}
                                    colorClass="bg-green-500"
                                />
                                <AttendanceBar
                                    label="Tidak Hadir"
                                    count={attendanceSummary.tidak_hadir}
                                    max={attendanceMax}
                                    colorClass="bg-red-500"
                                />
                                <AttendanceBar
                                    label="Izin"
                                    count={attendanceSummary.izin}
                                    max={attendanceMax}
                                    colorClass="bg-pln-yellow"
                                />
                                <AttendanceBar
                                    label="Belum Diisi"
                                    count={attendanceSummary.belum_diisi}
                                    max={attendanceMax}
                                    colorClass="bg-slate-300"
                                />
                            </div>
                        </Card>

                        <Card className="p-5">
                            <h3 className="text-sm font-semibold text-pln-navy">
                                Job & PR Mendekati Berakhir
                            </h3>
                            <p className="mt-1 text-xs text-slate-400">
                                dalam 14 hari ke depan
                            </p>
                            <div className="mt-4 space-y-3">
                                {upcomingJobPeriods.length === 0 && (
                                    <p className="text-sm text-slate-500">
                                        Tidak ada PR yang mendekati berakhir.
                                    </p>
                                )}
                                {upcomingJobPeriods.map((period) => (
                                    <Link
                                        key={period.id}
                                        href={route(
                                            "job-periods.show",
                                            period.id,
                                        )}
                                        className="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 hover:bg-slate-50"
                                    >
                                        <div>
                                            <div className="text-sm font-medium text-pln-navy">
                                                {period.job_nama_pekerjaan}
                                            </div>
                                            <div className="text-xs text-slate-400">
                                                {period.no_dokumen}
                                            </div>
                                        </div>
                                        <span className="text-xs font-medium text-pln-blue">
                                            {period.tanggal_selesai}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </Card>
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Card className="p-5">
                            <h3 className="text-sm font-semibold text-pln-navy">
                                Karyawan Terbaru
                            </h3>
                            <div className="mt-4 space-y-3">
                                {recentEmployees.length === 0 && (
                                    <p className="text-sm text-slate-500">
                                        Belum ada data karyawan.
                                    </p>
                                )}
                                {recentEmployees.map((employee) => (
                                    <Link
                                        key={employee.id}
                                        href={route(
                                            "employees.show",
                                            employee.id,
                                        )}
                                        className="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 hover:bg-slate-50"
                                    >
                                        <span className="text-sm font-medium text-pln-navy">
                                            {employee.nama}
                                        </span>
                                        <span className="text-xs text-slate-400">
                                            {employee.created_at}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </Card>

                        <Card className="p-5">
                            <h3 className="text-sm font-semibold text-pln-navy">
                                Job Terbaru
                            </h3>
                            <div className="mt-4 space-y-3">
                                {recentJobs.length === 0 && (
                                    <p className="text-sm text-slate-500">
                                        Belum ada data job.
                                    </p>
                                )}
                                {recentJobs.map((job) => (
                                    <Link
                                        key={job.id}
                                        href={route("jobs.show", job.id)}
                                        className="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 hover:bg-slate-50"
                                    >
                                        <span className="text-sm font-medium text-pln-navy">
                                            {job.nama_pekerjaan}
                                        </span>
                                        <span className="text-xs text-slate-400">
                                            {job.created_at}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </Card>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
