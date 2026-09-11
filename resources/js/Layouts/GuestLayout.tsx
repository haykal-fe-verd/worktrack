import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-gradient-to-b from-pln-blue/10 to-slate-50 pt-6 sm:justify-center sm:pt-0">
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-20 w-20 fill-current text-pln-blue" />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden rounded-2xl bg-white px-6 py-4 shadow-lg sm:max-w-md">
                {children}
            </div>
        </div>
    );
}
