import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { ChevronLeft, ChevronRight, MoreHorizontal } from 'lucide-react';

import { cn } from '@/lib/utils';
import { ButtonProps, buttonVariants } from '@/Components/ui/button';

const Pagination = ({ className, ...props }: React.ComponentProps<'nav'>) => (
    <nav
        role="navigation"
        aria-label="pagination"
        className={cn('mx-auto flex w-full justify-center', className)}
        {...props}
    />
);
Pagination.displayName = 'Pagination';

const PaginationContent = React.forwardRef<
    HTMLUListElement,
    React.ComponentProps<'ul'>
>(({ className, ...props }, ref) => (
    <ul
        ref={ref}
        className={cn('flex flex-row flex-wrap items-center gap-1', className)}
        {...props}
    />
));
PaginationContent.displayName = 'PaginationContent';

const PaginationItem = React.forwardRef<
    HTMLLIElement,
    React.ComponentProps<'li'>
>(({ className, ...props }, ref) => (
    <li ref={ref} className={cn('', className)} {...props} />
));
PaginationItem.displayName = 'PaginationItem';

type PaginationLinkProps = {
    isActive?: boolean;
    asChild?: boolean;
} & Pick<ButtonProps, 'size'> &
    React.ComponentProps<'a'>;

const PaginationLink = ({
    className,
    isActive,
    size = 'icon',
    asChild = false,
    children,
    ...props
}: PaginationLinkProps) => {
    const Comp = asChild ? Slot : 'a';

    return (
        <Comp
            aria-current={isActive ? 'page' : undefined}
            className={cn(
                buttonVariants({
                    variant: isActive ? 'outline' : 'ghost',
                    size,
                }),
                className,
            )}
            {...props}
        >
            {children}
        </Comp>
    );
};
PaginationLink.displayName = 'PaginationLink';

const PaginationPrevious = ({
    className,
    children,
    asChild,
    ...props
}: React.ComponentProps<typeof PaginationLink>) => (
    <PaginationLink
        aria-label="Sebelumnya"
        size="default"
        asChild={asChild}
        className={cn('gap-1 pl-2.5', className)}
        {...props}
    >
        {asChild ? (
            children
        ) : (
            <>
                <ChevronLeft className="h-4 w-4" />
                <span>Sebelumnya</span>
            </>
        )}
    </PaginationLink>
);
PaginationPrevious.displayName = 'PaginationPrevious';

const PaginationNext = ({
    className,
    children,
    asChild,
    ...props
}: React.ComponentProps<typeof PaginationLink>) => (
    <PaginationLink
        aria-label="Berikutnya"
        size="default"
        asChild={asChild}
        className={cn('gap-1 pr-2.5', className)}
        {...props}
    >
        {asChild ? (
            children
        ) : (
            <>
                <span>Berikutnya</span>
                <ChevronRight className="h-4 w-4" />
            </>
        )}
    </PaginationLink>
);
PaginationNext.displayName = 'PaginationNext';

const PaginationEllipsis = ({
    className,
    ...props
}: React.ComponentProps<'span'>) => (
    <span
        aria-hidden
        className={cn('flex h-9 w-9 items-center justify-center', className)}
        {...props}
    >
        <MoreHorizontal className="h-4 w-4" />
        <span className="sr-only">Halaman lainnya</span>
    </span>
);
PaginationEllipsis.displayName = 'PaginationEllipsis';

export {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
};
