import { useForm, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useRef, useState } from 'react';
import { SaleTransitionDialog } from '@/components/leads/sale-transition-dialog';
import { Button } from '@/components/ui/button';
import { complete } from '@/routes/leads';
import type { Lead } from '@/types/lead';
import type { LeadCategory } from '@/types/lead-category';

export function CompleteLeadDialog({
    lead,
    category,
    page,
}: {
    lead: Lead;
    category: LeadCategory | null;
    page: number;
}) {
    const { selectedCategory } = usePage<{
        selectedCategory?: LeadCategory | null;
    }>().props;
    const [open, setOpen] = useState(false);
    const [requestError, setRequestError] = useState<string>();
    const submitting = useRef(false);
    const form = useForm({ confirm_completion: true });
    const completed = lead.category.is_sale_completed;
    function confirm() {
        if (submitting.current || form.processing) return;
        submitting.current = true;
        setRequestError(undefined);
        form.submit(
            complete(lead.id, {
                query: { page, category: selectedCategory?.id },
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    requestAnimationFrame(() =>
                        document
                            .getElementById('leads-content')
                            ?.focus({ preventScroll: true }),
                    );
                },
                onHttpException: () => {
                    setRequestError(
                        'Não foi possível finalizar este lead. Atualize a página e verifique sua sessão.',
                    );
                    return false;
                },
                onNetworkError: () => {
                    setRequestError(
                        'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.',
                    );
                    return false;
                },
                onFinish: () => {
                    submitting.current = false;
                },
            },
        );
    }
    return (
        <>
            <Button
                type="button"
                disabled={completed || !category}
                aria-label={
                    completed
                        ? `Lead finalizado: ${lead.name}`
                        : `Finalizar ${lead.name}`
                }
                onClick={() => {
                    form.clearErrors();
                    setRequestError(undefined);
                    setOpen(true);
                }}
                className={`relative h-11 w-full rounded-xl ${completed ? 'bg-primary text-white disabled:opacity-100' : 'bg-[var(--dashboard-green)] text-[#0d2820] hover:bg-[#51e2b1]'}`}
            >
                <Check className="size-4" aria-hidden="true" />
                {completed && (
                    <span className="text-xs font-medium">Finalizado</span>
                )}
            </Button>
            <SaleTransitionDialog
                open={open}
                onOpenChange={(next) => {
                    if (!submitting.current) setOpen(next);
                }}
                leadName={lead.name}
                categoryName={category?.name ?? ''}
                transition="complete"
                processing={form.processing}
                error={Object.values(form.errors)[0] || requestError}
                onConfirm={confirm}
            />
        </>
    );
}
