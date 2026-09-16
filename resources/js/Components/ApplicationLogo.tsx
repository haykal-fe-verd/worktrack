import { cn } from '@/lib/utils';
import { Zap } from 'lucide-react';
import { HTMLAttributes } from 'react';

export default function ApplicationLogo({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            {...props}
            className={cn(
                'flex aspect-square items-center justify-center rounded-xl bg-pln-blue',
                className,
            )}
        >
            <Zap
                className="h-[64%] w-[64%] text-white"
                strokeWidth={2.25}
            />
        </div>
    );
}
