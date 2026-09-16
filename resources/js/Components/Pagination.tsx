import {
    Pagination as PaginationRoot,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from '@/Components/ui/pagination';
import { PaginationLink as PaginationLinkData } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export default function Pagination({ links }: { links: PaginationLinkData[] }) {
    if (links.length < 3) {
        return null;
    }

    const previous = links[0];
    const next = links[links.length - 1];
    const pages = links.slice(1, -1);

    return (
        <PaginationRoot className="mt-4 justify-center">
            <PaginationContent>
                <PaginationItem>
                    {previous.url ? (
                        <PaginationPrevious asChild>
                            <Link href={previous.url} preserveScroll>
                                <ChevronLeft className="h-4 w-4" />
                                <span>Sebelumnya</span>
                            </Link>
                        </PaginationPrevious>
                    ) : (
                        <PaginationPrevious
                            aria-disabled
                            className="pointer-events-none opacity-50"
                        />
                    )}
                </PaginationItem>

                {pages.map((link, index) =>
                    link.url ? (
                        <PaginationItem key={index}>
                            <PaginationLink isActive={link.active} asChild>
                                <Link href={link.url} preserveScroll>
                                    {link.label}
                                </Link>
                            </PaginationLink>
                        </PaginationItem>
                    ) : (
                        <PaginationItem key={index}>
                            <PaginationEllipsis />
                        </PaginationItem>
                    ),
                )}

                <PaginationItem>
                    {next.url ? (
                        <PaginationNext asChild>
                            <Link href={next.url} preserveScroll>
                                <span>Berikutnya</span>
                                <ChevronRight className="h-4 w-4" />
                            </Link>
                        </PaginationNext>
                    ) : (
                        <PaginationNext
                            aria-disabled
                            className="pointer-events-none opacity-50"
                        />
                    )}
                </PaginationItem>
            </PaginationContent>
        </PaginationRoot>
    );
}
