import { Head } from "@inertiajs/react";
import BentoCard from "@/Components/BentoCard";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import type { PageProps } from "@/types";

interface RoleCounts {
    admin: number;
    staff_input: number;
    viewer: number;
    total: number;
}

export default function Dashboard({
    roleCounts,
    employeeCount,
    jobCount,
    assignmentCount,
    belumDiisiCount,
}: PageProps<{
    roleCounts: RoleCounts;
    employeeCount: number;
    jobCount: number;
    assignmentCount: number;
    belumDiisiCount: number;
}>) {
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
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
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
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
