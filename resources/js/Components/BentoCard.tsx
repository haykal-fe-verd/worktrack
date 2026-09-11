import { PropsWithChildren } from 'react';

type Accent = 'blue' | 'yellow' | 'white';

interface BentoCardProps {
    title: string;
    badge?: string;
    accent?: Accent;
    className?: string;
}

const ACCENTS: Record<
    Accent,
    { card: string; label: string; badge: string }
> = {
    blue: {
        card: 'bg-pln-blue text-white',
        label: 'text-white/80',
        badge: 'bg-white/20 text-white',
    },
    yellow: {
        card: 'bg-pln-yellow text-pln-navy',
        label: 'text-pln-navy/70',
        badge: 'bg-pln-navy/10 text-pln-navy',
    },
    white: {
        card: 'border border-slate-200 bg-white text-pln-navy',
        label: 'text-slate-500',
        badge: 'bg-slate-100 text-slate-500',
    },
};

export default function BentoCard({
    title,
    badge,
    accent = 'white',
    className = '',
    children,
}: PropsWithChildren<BentoCardProps>) {
    const style = ACCENTS[accent];

    return (
        <div
            className={`flex flex-col justify-between rounded-2xl p-5 shadow-sm ${style.card} ${className}`}
        >
            <span
                className={`text-xs font-medium uppercase tracking-wide ${style.label}`}
            >
                {title}
            </span>

            <div className="mt-4">{children}</div>

            {badge && (
                <span
                    className={`mt-3 inline-block w-fit rounded-full px-2 py-0.5 text-[10px] font-semibold ${style.badge}`}
                >
                    {badge}
                </span>
            )}
        </div>
    );
}
