import { useForm } from '@inertiajs/react';
import { LoaderCircle, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { formatCount } from '@/components/dashboard/dashboard-data';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/categories';
import type { LeadCategoryMetric } from '@/types/lead-category';

export function DeleteCategoryDialog({
    category,
}: {
    category: LeadCategoryMetric;
}) {
    const [open, setOpen] = useState(false);
    const [requestError, setRequestError] = useState<string>();
    const cancelButton = useRef<HTMLButtonElement>(null);
    const submitting = useRef(false);
    const form = useForm({ confirmation: '' });
    const error =
        form.errors.confirmation ||
        (form.errors as Record<string, string | undefined>).form ||
        requestError;

    function changeOpen(next: boolean) {
        if (submitting.current || form.processing) return;
        if (next) {
            form.clearErrors();
            setRequestError(undefined);
        }
        setOpen(next);
    }

    function submit() {
        if (submitting.current || form.processing) return;
        submitting.current = true;
        setRequestError(undefined);
        form.transform(() => ({
            confirmation: category.deletion_confirmation ?? '',
        }));
        form.submit(destroy(category.id), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                requestAnimationFrame(() => {
                    document
                        .getElementById('categories-content')
                        ?.focus({ preventScroll: true });
                });
            },
            onError: () => setOpen(true),
            onHttpException: () => {
                setRequestError(
                    'Não foi possível excluir a categoria. Verifique sua sessão e tente novamente.',
                );
                return false;
            },
            onNetworkError: () => {
                setRequestError(
                    'Não foi possível conectar ao servidor. A categoria permanece na listagem; verifique sua conexão antes de tentar novamente.',
                );
                return false;
            },
            onFinish: () => {
                submitting.current = false;
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={`Excluir ${category.name}`}
                    title="Excluir categoria"
                    className="size-9 rounded-xl border border-white/10 bg-white/[0.025] text-[#e5a6b2] hover:bg-[#a64a5e]/15 hover:text-[#ffd5df]"
                >
                    <Trash2 className="size-3.5" aria-hidden="true" />
                </Button>
            </DialogTrigger>
            <DialogContent
                role="alertdialog"
                closeLabel="Fechar confirmação"
                closeDisabled={form.processing}
                aria-busy={form.processing}
                className="dashboard-theme max-h-[calc(100dvh-2rem)] gap-6 overflow-y-auto rounded-3xl border-white/10 bg-card p-5 text-foreground shadow-2xl sm:max-w-[440px] sm:p-7"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    cancelButton.current?.focus();
                }}
                onEscapeKeyDown={(event) => {
                    if (submitting.current) event.preventDefault();
                }}
                onInteractOutside={(event) => event.preventDefault()}
            >
                <DialogHeader className="gap-3 text-left">
                    <DialogTitle className="pr-6 text-xl leading-snug font-semibold tracking-tight [overflow-wrap:anywhere]">
                        Deseja excluir &quot;{category.name}&quot;?
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed">
                        Esta ação excluirá permanentemente a categoria e{' '}
                        {formatCount(category.leads_count)}{' '}
                        {category.leads_count === 1
                            ? 'lead vinculado'
                            : 'leads vinculados'}
                        . Não será possível desfazer.
                        <span className="mt-3 block text-xs">
                            A quantidade é conferida novamente ao confirmar. Se
                            houver alteração, revise os dados antes de tentar
                            novamente.
                        </span>
                    </DialogDescription>
                </DialogHeader>
                {error && (
                    <div role="alert">
                        <InputError
                            message={error}
                            className="text-[#ffb4c0]"
                        />
                    </div>
                )}
                <DialogFooter className="gap-3">
                    <Button
                        ref={cancelButton}
                        type="button"
                        variant="outline"
                        disabled={form.processing}
                        onClick={() => changeOpen(false)}
                        className="min-h-11 rounded-xl border-white/10 bg-secondary/60"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={submit}
                        className="min-h-11 rounded-xl bg-[#a33c50] text-white hover:bg-[#b8465d]"
                    >
                        {form.processing && (
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        )}
                        {form.processing
                            ? 'Excluindo categoria...'
                            : 'Excluir categoria'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
