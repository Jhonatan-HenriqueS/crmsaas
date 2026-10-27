import { router, useForm } from '@inertiajs/react';
import { LoaderCircle, LogOut } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
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
import { logout } from '@/routes';

export function LogoutDialog() {
    const [open, setOpen] = useState(false);
    const [error, setError] = useState<string>();
    const cancel = useRef<HTMLButtonElement>(null);
    const submitting = useRef(false);
    const form = useForm<Record<string, never>>({});

    function changeOpen(next: boolean) {
        if (submitting.current || form.processing) return;
        if (next) setError(undefined);
        setOpen(next);
    }

    function confirm() {
        if (submitting.current || form.processing) return;
        submitting.current = true;
        setError(undefined);
        form.submit(logout(), {
            preserveState: false,
            preserveScroll: false,
            replace: true,
            onSuccess: (page) => {
                if (!page.clearHistory) router.clearHistory();
                setOpen(false);
            },
            onError: () =>
                setError(
                    'Não foi possível confirmar a saída. Atualize a página e tente novamente.',
                ),
            onHttpException: (response) => {
                setError(
                    response.status === 419 || response.status === 401
                        ? 'Sua sessão expirou ou foi alterada. Atualize a página e tente novamente.'
                        : 'Não foi possível sair da conta. Tente novamente.',
                );
                return false;
            },
            onNetworkError: () => {
                setError(
                    'Não foi possível confirmar a saída. Verifique sua conexão e atualize a página antes de tentar novamente.',
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
                    className="h-10 w-full justify-start gap-2.5 px-2 text-xs text-muted-foreground hover:text-foreground"
                >
                    <LogOut className="size-4" aria-hidden="true" />
                    Sair da conta
                </Button>
            </DialogTrigger>
            <DialogContent
                role="alertdialog"
                closeLabel="Fechar confirmação de saída"
                closeDisabled={form.processing}
                aria-busy={form.processing}
                className="dashboard-theme max-h-[calc(100dvh-2rem)] min-w-0 gap-6 overflow-y-auto rounded-3xl border-white/10 bg-card p-5 [overflow-wrap:anywhere] text-foreground shadow-2xl sm:max-w-[440px] sm:p-7"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    cancel.current?.focus();
                }}
                onEscapeKeyDown={(event) => {
                    if (submitting.current) event.preventDefault();
                }}
                onInteractOutside={(event) => event.preventDefault()}
            >
                <DialogHeader className="gap-3 text-left">
                    <DialogTitle className="pr-6 text-xl leading-snug font-semibold tracking-tight">
                        Deseja sair da sua conta?
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed">
                        Sua sessão será encerrada neste dispositivo. Para
                        acessar o CRM novamente, será necessário fazer login.
                        Seus dados permanecerão salvos.
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
                        disabled={form.processing}
                        onClick={() => changeOpen(false)}
                        className="min-h-11 min-w-0 rounded-xl border-white/10 bg-secondary/60 whitespace-normal"
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={confirm}
                        className="min-h-11 min-w-0 rounded-xl bg-primary whitespace-normal text-white hover:bg-primary/90"
                    >
                        {form.processing && (
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        )}
                        {form.processing ? 'Saindo...' : 'Sair da conta'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
