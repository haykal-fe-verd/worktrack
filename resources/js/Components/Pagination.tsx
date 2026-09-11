import { Link } from '@inertiajs/react';
import { PaginationLink } from '@/types';

export default function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) {
        return null;
    }

    return (
        <div className="mt-4 flex flex-wrap gap-1">
            {links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={`rounded px-3 py-1 text-sm ${
                            link.active
                                ? 'bg-pln-blue text-white'
                                : 'text-slate-600 hover:bg-slate-100'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={index}
                        className="cursor-not-allowed rounded px-3 py-1 text-sm text-slate-300"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </div>
    );
}
