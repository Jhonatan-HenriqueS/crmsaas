import { useForm } from '@inertiajs/react';
import { LoaderCircle, Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { LeadCategorySelector } from '@/components/leads/lead-category-selector';
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
import {
    leadCreateClass,
    leadInputClass,
    parseLeadMoney,
} from '@/lib/lead-format';
import { store } from '@/routes/leads';
import type { LeadCategory } from '@/types/lead-category';

type LeadForm = {
    name: string;
    description: string;
    category_id: string;
    first_contact_date: string;
    next_contact_date: string;
    potential_amount: string;
    charged_amount: string;
};

const emptyForm: LeadForm = {
    name: '',
    description: '',
    category_id: '',
    first_contact_date: '',
    next_contact_date: '',
    potential_amount: '',
    charged_amount: '',
};

export function CreateLeadDialog() {
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<LeadCategory | null>(null);
    const [requestError, setRequestError] = useState<string>();
    const submitting = useRef(false);
    const form = useForm<LeadForm>(emptyForm);
    const generalError = (form.errors as Record<string, string | undefined>)
        .form;

    function changeOpen(next: boolean) {
        if (form.processing || submitting.current) return;
        if (next) {
            form.resetAndClearErrors();
            setSelected(null);
            setRequestError(undefined);
        }
        setOpen(next);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (submitting.current || form.processing) return;
        form.clearErrors();
        const potential = parseLeadMoney(form.data.potential_amount);
        const charged = parseLeadMoney(form.data.charged_amount);
        if (potential === undefined || charged === undefined) {
            if (potential === undefined)
                form.setError(
                    'potential_amount',
                    'Use um valor até R$ 9.999.999.999,99, com vírgula para os centavos.',
                );
            if (charged === undefined)
                form.setError(
                    'charged_amount',
                    'Use um valor até R$ 9.999.999.999,99, com vírgula para os centavos.',
                );
            return;
        }
        submitting.current = true;
        setRequestError(undefined);
        form.transform((data) => ({
            ...data,
            description: data.description.trim() || null,
            next_contact_date: data.next_contact_date || null,
            potential_amount: potential,
            charged_amount: charged,
        }));
        form.submit(store(), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                form.reset();
                setSelected(null);
            },
            onError: () => setOpen(true),
            onHttpException: () => {
                setRequestError(
                    'Não foi possível criar o lead. Verifique sua sessão e tente novamente.',
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
        });
    }

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    disabled={form.processing}
                    className="min-h-11 w-full rounded-xl bg-primary px-5 text-white shadow-sm hover:bg-primary/90 sm:w-auto"
                >
                    <Plus className="size-4" aria-hidden="true" />
                    Criar lead
                </Button>
            </DialogTrigger>
            <DialogContent
                closeLabel="Fechar cadastro de lead"
                closeDisabled={form.processing}
                aria-busy={form.processing}
                onEscapeKeyDown={(event) => {
                    if (submitting.current) event.preventDefault();
                }}
                onInteractOutside={(event) => {
                    if (submitting.current) event.preventDefault();
                }}
                className="dashboard-theme max-h-[calc(100dvh-2rem)] gap-6 overflow-y-auto rounded-3xl border-white/10 bg-card p-5 text-foreground shadow-2xl sm:max-w-[620px] sm:p-7"
            >
                <DialogHeader className="gap-3 text-left">
                    <p className="text-[10px] font-medium tracking-[0.2em] text-muted-foreground uppercase">
                        Uma nova oportunidade
                    </p>
                    <DialogTitle className="pr-6 text-2xl font-semibold tracking-tight">
                        Novo lead
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed">
                        Cadastre uma oportunidade, organize sua etapa e
                        acompanhe os próximos contatos.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-2.5">
                        <Label htmlFor="lead-name">
                            Nome do lead{' '}
                            <span className="text-muted-foreground">*</span>
                        </Label>
                        <Input
                            id="lead-name"
                            name="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                            maxLength={120}
                            disabled={form.processing}
                            autoComplete="off"
                            placeholder="Ex.: Empresa Horizonte"
                            aria-invalid={!!form.errors.name}
                            aria-describedby="lead-name-error"
                            className={leadInputClass}
                        />
                        <InputError
                            id="lead-name-error"
                            message={form.errors.name}
                            className="text-[#ffb4c0]"
                        />
                    </div>
                    <div className="space-y-2.5">
                        <Label htmlFor="lead-description">
                            Descrição{' '}
                            <span className="text-xs font-normal text-muted-foreground">
                                (opcional)
                            </span>
                        </Label>
                        <textarea
                            id="lead-description"
                            name="description"
                            rows={3}
                            maxLength={5000}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData('description', e.target.value)
                            }
                            disabled={form.processing}
                            placeholder="Anote o contexto e os detalhes desta oportunidade."
                            aria-invalid={!!form.errors.description}
                            aria-describedby="lead-description-error"
                            className={`${leadInputClass} block h-auto min-h-24 w-full resize-y border px-3 py-3 outline-none placeholder:text-muted-foreground disabled:opacity-50`}
                        />
                        <InputError
                            id="lead-description-error"
                            message={form.errors.description}
                            className="text-[#ffb4c0]"
                        />
                    </div>
                    <div className="space-y-2.5">
                        <Label htmlFor="lead-category">
                            Etapa do lead{' '}
                            <span className="text-muted-foreground">*</span>
                        </Label>
                        <LeadCategorySelector
                            selected={selected}
                            onSelect={(category) => {
                                setSelected(category);
                                form.setData(
                                    'category_id',
                                    String(category.id),
                                );
                                form.clearErrors('category_id');
                            }}
                            disabled={form.processing}
                            invalid={!!form.errors.category_id}
                            describedBy="lead-category-error"
                        />
                        <InputError
                            id="lead-category-error"
                            message={form.errors.category_id}
                            className="text-[#ffb4c0]"
                        />
                    </div>
                    <div className="grid gap-5 min-[540px]:grid-cols-2">
                        <div className="min-w-0 space-y-2.5">
                            <Label htmlFor="lead-first-date">
                                Primeiro contato{' '}
                                <span className="text-muted-foreground">*</span>
                            </Label>
                            <Input
                                id="lead-first-date"
                                name="first_contact_date"
                                type="date"
                                lang="pt-BR"
                                required
                                min="0001-01-01"
                                max="9999-12-31"
                                value={form.data.first_contact_date}
                                onChange={(e) =>
                                    form.setData(
                                        'first_contact_date',
                                        e.target.value,
                                    )
                                }
                                disabled={form.processing}
                                aria-invalid={!!form.errors.first_contact_date}
                                aria-describedby="lead-first-date-error"
                                className={`${leadInputClass} [color-scheme:dark]`}
                            />
                            <InputError
                                id="lead-first-date-error"
                                message={form.errors.first_contact_date}
                                className="text-[#ffb4c0]"
                            />
                        </div>
                        <div className="min-w-0 space-y-2.5">
                            <Label htmlFor="lead-next-date">
                                Próximo contato{' '}
                                <span className="text-xs font-normal text-muted-foreground">
                                    (opcional)
                                </span>
                            </Label>
                            <Input
                                id="lead-next-date"
                                name="next_contact_date"
                                type="date"
                                lang="pt-BR"
                                min={
                                    form.data.first_contact_date || '0001-01-01'
                                }
                                max="9999-12-31"
                                value={form.data.next_contact_date}
                                onChange={(e) =>
                                    form.setData(
                                        'next_contact_date',
                                        e.target.value,
                                    )
                                }
                                disabled={form.processing}
                                aria-invalid={!!form.errors.next_contact_date}
                                aria-describedby="lead-next-date-error"
                                className={`${leadInputClass} [color-scheme:dark]`}
                            />
                            <InputError
                                id="lead-next-date-error"
                                message={form.errors.next_contact_date}
                                className="text-[#ffb4c0]"
                            />
                        </div>
                    </div>
                    <div className="grid gap-5 min-[540px]:grid-cols-2">
                        {(['potential_amount', 'charged_amount'] as const).map(
                            (field) => (
                                <div
                                    key={field}
                                    className="min-w-0 space-y-2.5"
                                >
                                    <Label htmlFor={`lead-${field}`}>
                                        {field === 'potential_amount'
                                            ? 'Valor potencial'
                                            : 'Valor cobrado'}{' '}
                                        <span className="text-xs font-normal text-muted-foreground">
                                            (opcional)
                                        </span>
                                    </Label>
                                    <div className="relative">
                                        <span
                                            aria-hidden="true"
                                            className="pointer-events-none absolute top-3 left-3 text-sm text-muted-foreground"
                                        >
                                            R$
                                        </span>
                                        <Input
                                            id={`lead-${field}`}
                                            name={field}
                                            inputMode="decimal"
                                            autoComplete="off"
                                            maxLength={24}
                                            value={form.data[field]}
                                            onChange={(e) =>
                                                form.setData(
                                                    field,
                                                    e.target.value,
                                                )
                                            }
                                            disabled={form.processing}
                                            placeholder="0,00"
                                            aria-invalid={!!form.errors[field]}
                                            aria-describedby={`lead-${field}-error lead-money-help`}
                                            className={`${leadInputClass} pl-10 tabular-nums`}
                                        />
                                    </div>
                                    <InputError
                                        id={`lead-${field}-error`}
                                        message={form.errors[field]}
                                        className="text-[#ffb4c0]"
                                    />
                                </div>
                            ),
                        )}
                    </div>
                    <p
                        id="lead-money-help"
                        className="text-xs leading-relaxed text-muted-foreground"
                    >
                        Use vírgula para os centavos. Ex.: 1.500,00. Deixe em
                        branco quando não informado.
                    </p>
                    <InputError
                        message={generalError || requestError}
                        role="alert"
                        className="text-[#ffb4c0]"
                    />
                    <Button
                        type="submit"
                        disabled={form.processing}
                        className={`${leadCreateClass} h-12 w-full`}
                    >
                        {form.processing && (
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        )}
                        {form.processing ? 'Criando lead...' : 'Cadastrar lead'}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
