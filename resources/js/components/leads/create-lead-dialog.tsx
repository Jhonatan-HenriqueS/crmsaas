import { router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle, Pencil, Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { LeadCategorySelector } from '@/components/leads/lead-category-selector';
import { LeadMapsUrlField } from '@/components/leads/lead-maps-url-field';
import { LeadMoneyField } from '@/components/leads/lead-money-field';
import { SaleTransitionDialog } from '@/components/leads/sale-transition-dialog';
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
    formatLeadMoneyInput,
    isPositiveLeadMoney,
    parseLeadMoney,
} from '@/lib/lead-format';
import { leadTransition } from '@/lib/lead-transition';
import type { LeadTransition } from '@/lib/lead-transition';
import { store, update } from '@/routes/leads';
import type { Lead } from '@/types/lead';
import type { LeadCategory } from '@/types/lead-category';

type LeadForm = {
    name: string;
    description: string;
    category_id: string;
    first_contact_date: string;
    next_contact_date: string;
    potential_amount: string;
    charged_amount: string;
    maps_url: string;
};

const emptyForm: LeadForm = {
    name: '',
    description: '',
    category_id: '',
    first_contact_date: '',
    next_contact_date: '',
    potential_amount: '',
    charged_amount: '',
    maps_url: '',
};

type LeadDialogProps =
    | { mode: 'create' }
    | { mode: 'edit'; lead: Lead; page: number };

export function CreateLeadDialog(props: LeadDialogProps) {
    const editing = props.mode === 'edit';
    const initialData: LeadForm = editing
        ? {
              name: props.lead.name,
              description: props.lead.description ?? '',
              category_id: String(props.lead.category_id),
              first_contact_date: props.lead.first_contact_date,
              next_contact_date: props.lead.next_contact_date ?? '',
              potential_amount: formatLeadMoneyInput(
                  props.lead.potential_amount,
              ),
              charged_amount: formatLeadMoneyInput(props.lead.charged_amount),
              maps_url: props.lead.maps_url ?? '',
          }
        : emptyForm;
    const { selectedCategory } = usePage<{
        selectedCategory?: LeadCategory | null;
    }>().props;
    const [open, setOpen] = useState(false);
    const [selected, setSelected] = useState<LeadCategory | null>(null);
    const [requestError, setRequestError] = useState<string>();
    const [transition, setTransition] = useState<LeadTransition | null>(null);
    const submitting = useRef(false);
    const form = useForm<LeadForm>(initialData);
    const actionErrors = form.errors as Record<string, string | undefined>;
    const generalError =
        actionErrors.form ||
        actionErrors.confirm_completion ||
        actionErrors.confirm_reopening;

    function changeOpen(next: boolean) {
        if (form.processing || submitting.current) return;
        if (next) {
            form.clearErrors();
            form.setDefaults(initialData);
            form.setData(initialData);
            setSelected(editing ? props.lead.category : null);
            setRequestError(undefined);
            setTransition(null);
        }
        setOpen(next);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        send(null);
    }

    function send(confirmation: LeadTransition | null) {
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
        if (selected?.is_sale_completed && !isPositiveLeadMoney(charged)) {
            form.setError(
                'charged_amount',
                'Informe um valor cobrado maior que zero para manter o lead em Finalizado.',
            );
            return;
        }
        const requiredTransition = leadTransition(
            editing ? props.lead.category : null,
            selected,
        );
        if (
            requiredTransition !== null &&
            confirmation !== requiredTransition
        ) {
            setTransition(requiredTransition);
            return;
        }
        submitting.current = true;
        setRequestError(undefined);
        form.transform((data) => ({
            ...data,
            description: data.description.trim() || null,
            next_contact_date: data.next_contact_date || null,
            maps_url: data.maps_url.trim() || null,
            potential_amount: potential,
            charged_amount: charged,
            confirm_completion: confirmation === 'complete',
            confirm_reopening: confirmation === 'reopen',
        }));
        form.submit(
            editing
                ? update(props.lead.id, {
                      query: {
                          page: props.page,
                          category: selectedCategory?.id,
                      },
                  })
                : store({ query: { category: selectedCategory?.id } }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setOpen(false);
                    form.reset();
                    setSelected(null);
                    setTransition(null);
                },
                onError: () => setOpen(true),
                onHttpException: (response) => {
                    if (editing && response.status === 404) {
                        toast.error(
                            'Este lead não está mais disponível. A listagem será atualizada.',
                        );
                        setTimeout(
                            () =>
                                router.reload({
                                    only: [
                                        'leads',
                                        'selectedCategory',
                                        'completionCategory',
                                    ],
                                }),
                            0,
                        );
                        return false;
                    }
                    setRequestError(
                        editing
                            ? 'Não foi possível salvar o lead. Verifique sua sessão e tente novamente.'
                            : 'Não foi possível criar o lead. Verifique sua sessão e tente novamente.',
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
                {editing ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        disabled={form.processing}
                        aria-label={`Editar ${props.lead.name}`}
                        title="Editar lead"
                        className="size-9 rounded-xl border-white/10 bg-white/[0.02] text-muted-foreground hover:bg-primary/15 hover:text-foreground"
                    >
                        <Pencil className="size-3.5" aria-hidden="true" />
                    </Button>
                ) : (
                    <Button
                        type="button"
                        disabled={form.processing}
                        className="min-h-11 w-full rounded-xl bg-primary px-5 text-white shadow-sm hover:bg-primary/90 sm:w-auto"
                    >
                        <Plus className="size-4" aria-hidden="true" />
                        Criar lead
                    </Button>
                )}
            </DialogTrigger>
            <DialogContent
                closeLabel={
                    editing
                        ? 'Fechar edição de lead'
                        : 'Fechar cadastro de lead'
                }
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
                        {editing ? 'Sua oportunidade' : 'Uma nova oportunidade'}
                    </p>
                    <DialogTitle className="pr-6 text-2xl font-semibold tracking-tight">
                        {editing ? 'Editar lead' : 'Novo lead'}
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed">
                        {editing
                            ? 'Atualize as informações desta oportunidade e mantenha seus contatos organizados.'
                            : 'Cadastre uma oportunidade, organize sua etapa e acompanhe os próximos contatos.'}
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
                    <div className="space-y-2.5">
                        <Label htmlFor="lead-maps-url">
                            Link do Google Maps{' '}
                            <span className="text-xs font-normal text-muted-foreground">
                                (opcional)
                            </span>
                        </Label>
                        <LeadMapsUrlField
                            id="lead-maps-url"
                            value={form.data.maps_url}
                            onChange={(value) =>
                                form.setData('maps_url', value)
                            }
                            onPasteLink={(link) => {
                                form.setData('maps_url', link);
                                form.clearErrors('maps_url');
                            }}
                            disabled={form.processing}
                            error={form.errors.maps_url}
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
                                <LeadMoneyField
                                    key={field}
                                    id={`lead-${field}`}
                                    name={field}
                                    label={
                                        field === 'potential_amount'
                                            ? 'Valor potencial'
                                            : 'Valor cobrado'
                                    }
                                    hint={
                                        field === 'charged_amount' &&
                                        selected?.is_sale_completed
                                            ? '(obrigatório)'
                                            : '(opcional)'
                                    }
                                    value={form.data[field]}
                                    onChange={(value) =>
                                        form.setData(field, value)
                                    }
                                    disabled={form.processing}
                                    error={form.errors[field]}
                                    describedBy="lead-money-help"
                                />
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
                        className={`${leadCreateClass} h-12 w-full [overflow-wrap:anywhere] whitespace-normal`}
                    >
                        {form.processing && (
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                        )}
                        {form.processing
                            ? editing
                                ? 'Salvando alterações...'
                                : 'Criando lead...'
                            : editing
                              ? 'Salvar alterações'
                              : 'Cadastrar lead'}
                    </Button>
                </form>
                <SaleTransitionDialog
                    open={transition !== null}
                    onOpenChange={(next) => {
                        if (!next && !submitting.current) setTransition(null);
                    }}
                    leadName={form.data.name}
                    categoryName={selected?.name ?? ''}
                    transition={transition ?? 'complete'}
                    processing={form.processing}
                    error={Object.values(form.errors)[0] || requestError}
                    onConfirm={() => send(transition)}
                />
            </DialogContent>
        </Dialog>
    );
}
