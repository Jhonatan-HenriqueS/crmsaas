import { useForm } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { HexColorPicker } from 'react-colorful';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/categories';
import type { LeadCategory } from '@/types/lead-category';

type CategoryForm = { name: string; color: string };

type CategoryDialogProps = {
    visibleColors: string[];
} & (
    | { mode: 'create'; initialColor: string }
    | { mode: 'edit'; category: LeadCategory }
);

export function CategoryDialog(props: CategoryDialogProps) {
    const initialData =
        props.mode === 'edit'
            ? { name: props.category.name, color: props.category.color }
            : { name: '', color: props.initialColor };
    const initialColor = initialData.color;
    const [open, setOpen] = useState(false);
    const [requestError, setRequestError] = useState<string>();
    const submitting = useRef(false);
    const form = useForm<CategoryForm>(initialData);
    const canonicalColor = form.data.color.trim().toLowerCase();
    const validColor = /^#[0-9a-f]{6}$/.test(canonicalColor);
    const occupied =
        props.visibleColors.includes(canonicalColor) &&
        !(props.mode === 'edit' && canonicalColor === props.category.color);
    const unchanged =
        props.mode === 'edit' &&
        form.data.name === props.category.name &&
        canonicalColor === props.category.color;
    const colorError =
        form.errors.color ||
        (occupied
            ? 'Esta cor já está sendo utilizada por outra categoria. Escolha uma cor diferente.'
            : undefined) ||
        (!validColor
            ? 'Use # seguido de seis dígitos, como #6d54b5.'
            : undefined);
    const generalError = (form.errors as Record<string, string | undefined>)
        .form;

    function changeColor(color: string) {
        form.setData('color', color.toLowerCase());
        form.clearErrors('color');
    }

    function changeOpen(next: boolean) {
        if (form.processing || submitting.current) return;
        if (next) {
            form.clearErrors();
            form.setDefaults(initialData);
            form.setData(initialData);
            setRequestError(undefined);
        }
        setOpen(next);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (
            submitting.current ||
            form.processing ||
            !validColor ||
            occupied ||
            unchanged
        )
            return;
        submitting.current = true;
        setRequestError(undefined);

        form.submit(
            props.mode === 'edit' ? update(props.category.id) : store(),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    form.reset();
                },
                onError: () => setOpen(true),
                onHttpException: () => {
                    setRequestError(
                        'Não foi possível salvar a categoria. Verifique sua sessão e tente novamente.',
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
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                {props.mode === 'edit' ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={form.processing}
                        aria-label={`Editar ${props.category.name}`}
                        title="Editar categoria"
                        className="size-9 rounded-xl border border-white/10 bg-white/[0.025] text-muted-foreground hover:bg-primary/15 hover:text-foreground"
                    >
                        <Pencil className="size-3.5" aria-hidden="true" />
                    </Button>
                ) : (
                    <Button
                        className="min-h-11 w-full rounded-xl bg-primary px-5 text-white shadow-sm hover:bg-primary/90 sm:w-auto"
                        disabled={form.processing}
                    >
                        <Plus className="size-4" aria-hidden="true" />
                        Nova categoria
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent
                closeLabel="Fechar modal"
                closeDisabled={form.processing}
                aria-busy={form.processing}
                onEscapeKeyDown={(event) => {
                    if (submitting.current) event.preventDefault();
                }}
                onInteractOutside={(event) => {
                    if (submitting.current) event.preventDefault();
                }}
                className="dashboard-theme max-h-[calc(100dvh-2rem)] gap-6 overflow-y-auto rounded-3xl border-white/10 bg-card p-5 text-foreground shadow-2xl sm:max-w-[440px] sm:p-7"
            >
                <DialogHeader className="gap-3 text-left">
                    <p className="text-[10px] font-medium tracking-[0.2em] text-muted-foreground uppercase">
                        Categorias
                    </p>
                    <DialogTitle className="pr-6 text-2xl font-semibold tracking-tight">
                        {props.mode === 'edit'
                            ? 'Editar categoria'
                            : 'Nova categoria de lead'}
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed">
                        {props.mode === 'edit'
                            ? 'Atualize o nome e a cor para manter suas categorias organizadas.'
                            : 'Crie uma categoria para identificar a fase do seu lead e organize suas oportunidades por cor.'}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid gap-2.5 space-y-2.5">
                        <Label htmlFor="category-name">Nome da categoria</Label>
                        <Input
                            id="category-name"
                            name="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Vendido, reunião, cancelado..."
                            maxLength={120}
                            required
                            autoComplete="off"
                            disabled={form.processing}
                            aria-invalid={!!form.errors.name}
                            aria-describedby={
                                form.errors.name
                                    ? 'category-name-error'
                                    : undefined
                            }
                            className="h-12 rounded-xl border-white/10 bg-secondary/60 px-3.5 text-sm transition-[background-color,border-color,box-shadow] placeholder:text-muted-foreground/70 focus-visible:border-ring/40 focus-visible:bg-accent/70 focus-visible:shadow-[0_0_18px_#bca6ec1a] focus-visible:ring-0"
                        />
                        <InputError
                            id="category-name-error"
                            message={form.errors.name}
                            className="text-[#ffb4c0]"
                        />
                    </div>
                    <div className="space-y-3">
                        <Label id="category-color-label" htmlFor="category-hex">
                            Cor da categoria
                        </Label>
                        <div
                            className="category-color-picker mt-2.5 rounded-2xl border border-white/[0.07] bg-background/50 p-3"
                            role="group"
                            aria-labelledby="category-color-label"
                            inert={form.processing}
                        >
                            <HexColorPicker
                                color={
                                    validColor ? canonicalColor : initialColor
                                }
                                onChange={changeColor}
                                aria-label="Selecionar cor da categoria"
                            />
                        </div>
                        <div className="flex items-center gap-3">
                            <span
                                className="size-11 shrink-0 rounded-xl border border-white/20"
                                style={{
                                    backgroundColor: validColor
                                        ? canonicalColor
                                        : initialColor,
                                }}
                                role="img"
                                aria-label={`Prévia da cor ${validColor ? canonicalColor : initialColor}`}
                            />
                            <div className="min-w-0 flex-1">
                                <Label
                                    htmlFor="category-hex"
                                    className="mb-2 text-xs text-muted-foreground"
                                >
                                    Código HEX
                                </Label>
                                <Input
                                    id="category-hex"
                                    name="color"
                                    value={form.data.color}
                                    onChange={(event) =>
                                        changeColor(event.target.value)
                                    }
                                    maxLength={7}
                                    autoComplete="off"
                                    spellCheck={false}
                                    required
                                    disabled={form.processing}
                                    aria-invalid={!!colorError}
                                    aria-describedby="category-color-help category-color-error"
                                    className="h-10 rounded-lg border-white/10 bg-secondary/60 font-mono text-sm transition-[background-color,border-color,box-shadow] focus-visible:border-ring/40 focus-visible:bg-accent/70 focus-visible:shadow-[0_0_18px_#bca6ec1a] focus-visible:ring-0"
                                />
                            </div>
                        </div>
                        <p
                            id="category-color-help"
                            className="text-xs leading-relaxed text-muted-foreground"
                        >
                            Escolha livremente uma cor. Cada categoria da sua
                            conta deve ter uma cor diferente.
                        </p>
                        <InputError
                            id="category-color-error"
                            message={colorError}
                            className="text-[#ffb4c0]"
                        />
                    </div>
                    <InputError
                        message={generalError || requestError}
                        className="text-[#ffb4c0]"
                    />
                    <Button
                        type="submit"
                        disabled={
                            form.processing ||
                            !validColor ||
                            occupied ||
                            unchanged
                        }
                        className="h-12 w-full rounded-xl bg-[var(--dashboard-green)] text-sm font-semibold text-[#0d2820] shadow-sm hover:bg-[#51e2b1]"
                    >
                        {form.processing && (
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        )}
                        {form.processing
                            ? props.mode === 'edit'
                                ? 'Salvando alterações...'
                                : 'Criando categoria...'
                            : props.mode === 'edit'
                              ? 'Salvar alterações'
                              : 'Criar categoria'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
