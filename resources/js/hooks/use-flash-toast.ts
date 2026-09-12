import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { PageProps } from '@/types';

export default function useFlashToast() {
    const page = usePage<PageProps>();
    const { flash } = page.props;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [page]);
}
