import { LoaderCircle } from 'lucide-react';
import { useRef } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { LeadTransition } from '@/lib/lead-transition';

export function SaleTransitionDialog({
    open,
    onOpenChange,
    leadName,
    categoryName,
    transition,
    processing = false,
    error,
    onConfirm,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    leadName: string;
    categoryName: string;
    transition: LeadTransition;
    processing?: boolean;
    error?: string;
    onConfirm: () => void;
}) {
    const cancel = useRef<HTMLButtonElement>(null);
    const returnFocus = useRef<HTMLElement | null>(null);
    const completing = transition === 'complete';
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!processing) onOpenChange(next);
            }}
        >
            <DialogContent
                role="alertdialog"
                closeLabel="Fechar confirmação"
                closeDisabled={processing}
                aria-busy={processing}
                className="dashboard-theme max-h-[calc(100dvh-2rem)] gap-6 overflow-y-auto rounded-3xl border-white/10 bg-card p-5 text-foreground shadow-2xl sm:max-w-[440px] sm:p-7"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    returnFocus.current =
                        document.activeElement instanceof HTMLElement
                            ? document.activeElement
                            : null;
                    cancel.current?.focus();
                }}
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    requestAnimationFrame(
                        () =>
                            returnFocus.current?.isConnected &&
                            returnFocus.current.focus({ preventScroll: true }),
                    );
                }}
                onEscapeKeyDown={(event) => {
                    if (processing) event.preventDefault();
                }}
                onInteractOutside={(event) => event.preventDefault()}
            >
                <DialogHeader className="gap-3 text-left">
                    <DialogTitle className="pr-6 text-xl leading-snug font-semibold tracking-tight [overflow-wrap:anywhere]">
                        Deseja {completing ? 'finalizar' : 'reabrir'} &quot;
                        {leadName.trim() || 'este lead'}&quot;?
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed [overflow-wrap:anywhere]">
                        {completing ? (
                            <>
                                Confirme apenas se a venda foi concluída,
                                entregue e paga. Este lead será marcado como
                                vendido e passará para a categoria{' '}
                                {categoryName}.
                            </>
                        ) : (
                            <>
                                Este lead deixará de ser considerado vendido e
                                voltará para a etapa {categoryName}. Os
                                indicadores serão atualizados.
                            </>
                        )}
                    </DialogDescription>
                </DialogHeader>
                <InputError
                    message={error}
                    role="alert"
                    className="text-[#ffb4c0]"
                />
                <DialogFooter className="min-w-0 gap-3 sm:flex-wrap">
                    <Button
                        ref={cancel}
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => onOpenChange(false)}
                        className="min-h-11 min-w-0 rounded-xl border-white/10 bg-secondary/60 [overflow-wrap:anywhere] whitespace-normal"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        disabled={processing}
                        onClick={onConfirm}
                        className="min-h-11 min-w-0 rounded-xl bg-primary [overflow-wrap:anywhere] whitespace-normal text-white hover:bg-primary/90"
                    >
                        {processing && (
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        )}
                        {processing
                            ? 'Salvando...'
                            : completing
                              ? 'Confirmar venda'
                              : 'Confirmar reabertura'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
