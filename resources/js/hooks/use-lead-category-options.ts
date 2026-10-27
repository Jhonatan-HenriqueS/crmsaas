import { useHttp } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { categoryOptions } from '@/routes/leads';
import type { LeadCategoryOptions } from '@/types/lead';
import type { LeadCategory } from '@/types/lead-category';

/** Shared bounded category loading for lead forms and the list filter. */
export function useLeadCategoryOptions() {
    const [options, setOptions] = useState<LeadCategory[]>([]);
    const [cursor, setCursor] = useState<string | null>(null);
    const [loaded, setLoaded] = useState(false);
    const [error, setError] = useState<string>();
    const request = useHttp<Record<string, never>, LeadCategoryOptions>({});
    const generation = useRef(0);
    const loading = useRef(false);

    async function load(next: string | null, version: number) {
        if (loading.current) return;
        loading.current = true;
        setError(undefined);
        try {
            const result = await request.get(
                categoryOptions.url({ query: next ? { cursor: next } : {} }),
            );
            if (version !== generation.current) return;
            setOptions((current) =>
                next
                    ? [
                          ...current,
                          ...result.data.filter(
                              (item) =>
                                  !current.some(
                                      (existing) => existing.id === item.id,
                                  ),
                          ),
                      ]
                    : result.data,
            );
            setCursor(result.nextCursor);
            setLoaded(true);
        } catch {
            if (version === generation.current)
                setError(
                    'Não foi possível carregar as etapas. Tente novamente.',
                );
        } finally {
            if (version === generation.current) loading.current = false;
        }
    }

    function changeOpen(open: boolean) {
        generation.current++;
        request.cancel();
        loading.current = false;
        if (open) {
            setOptions([]);
            setLoaded(false);
            setCursor(null);
            setError(undefined);
            void load(null, generation.current);
        }
    }

    return {
        options,
        cursor,
        loaded,
        error,
        processing: request.processing,
        changeOpen,
        loadMore: () => load(cursor, generation.current),
    };
}
