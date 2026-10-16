import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { CrudOperation, FlashToast } from '@/types/ui';

const operationClasses: Record<CrudOperation, string> = {
    created: 'crud-toast-created',
    updated: 'crud-toast-updated',
    deleted: 'crud-toast-deleted',
};

export function useFlashToast(): void {
    useEffect(() => {
        const expirations = new Map<
            string | number,
            ReturnType<typeof setTimeout>
        >();
        const clearExpiration = (id: string | number) => {
            clearTimeout(expirations.get(id));
            expirations.delete(id);
        };
        const unsubscribe = router.on('flash', (event) => {
            const flash = event.detail.flash;
            const data = flash?.toast as FlashToast | undefined;

            if (!data) {
                return;
            }

            const id = toast[data.type](data.message, {
                duration: 5000,
                className: data.operation
                    ? operationClasses[data.operation]
                    : undefined,
                onDismiss: ({ id }) => clearExpiration(id),
                onAutoClose: ({ id }) => clearExpiration(id),
            });

            // Sonner pauses its timer on hover; this deadline keeps the five-second limit.
            expirations.set(
                id,
                setTimeout(() => {
                    toast.dismiss(id);
                    clearExpiration(id);
                }, 5000),
            );
        });

        return () => {
            unsubscribe();
            expirations.forEach((expiration) => clearTimeout(expiration));
        };
    }, []);
}
