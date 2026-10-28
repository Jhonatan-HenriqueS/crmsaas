import { useForm } from '@inertiajs/react';
import { LoaderCircle, Pencil } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { LeadMoneyField } from '@/components/leads/lead-money-field';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    formatLeadMoneyInput,
    isPositiveLeadMoney,
    leadCreateClass,
    leadInputClass,
    localCivilDate,
    parseLeadMoney,
} from '@/lib/lead-format';
import { destroy, update } from '@/routes/dashboard/goal';
import { dashboardEditButtonClass } from './dashboard-data';
import type { DashboardData } from './dashboard-data';

type DeadlineMode = 'days' | 'date';

export function DashboardGoalDialog({ data }: { data: DashboardData }) {
    const [open, setOpen] = useState(false);
    const [mode, setMode] = useState<DeadlineMode>('days');
    const [requestError, setRequestError] = useState<string>();
    const submitting = useRef(false);
    const form = useForm({
        goal_amount: '',
        deadline_days: '',
        deadline_date: '',
    });
    const removal = useForm({});
    const processing = form.processing || removal.processing;
    const generalError = (form.errors as Record<string, string | undefined>)
        .form;

    function changeOpen(next: boolean) {
        if (submitting.current) return;
        if (next) {
            form.clearErrors();
            form.setData({
                goal_amount: formatLeadMoneyInput(data.goalAmount),
                deadline_days: '',
                deadline_date: data.goalDeadline ?? '',
            });
            setMode(data.goalDeadline ? 'date' : 'days');
            setRequestError(undefined);
        }
        setOpen(next);
    }

    const visitOptions = {
        preserveScroll: true,
        preserveUrl: true,
        only: ['overview'],
        onSuccess: () => setOpen(false),
        onHttpException: () => {
            setRequestError(
                'Não foi possível salvar a meta. Atualize a página e verifique sua sessão.',
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
    };

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (submitting.current || processing) return;
        form.clearErrors();
        const amount = parseLeadMoney(form.data.goal_amount);
        if (!isPositiveLeadMoney(amount)) {
            form.setError(
                'goal_amount',
                'Informe um valor maior que zero, até R$ 9.999.999.999,99, com vírgula para os centavos.',
            );
            return;
        }
        submitting.current = true;
        setRequestError(undefined);
        form.transform((values) => ({
            goal_amount: amount,
            ...(mode === 'days'
                ? { deadline_days: values.deadline_days }
                : { deadline_date: values.deadline_date }),
        }));
        form.submit(update(), visitOptions);
    }

    function remove() {
        if (submitting.current || processing) return;
        submitting.current = true;
        setRequestError(undefined);
        removal.submit(destroy(), visitOptions);
    }

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className={dashboardEditButtonClass}
                    aria-label="Definir meta de faturamento"
                >
                    <Pencil className="size-4" aria-hidden="true" />
                </Button>
            </DialogTrigger>
            <DialogContent
                closeLabel="Fechar meta"
                closeDisabled={processing}
                aria-busy={processing}
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
                        Sua meta
                    </p>
                    <DialogTitle className="pr-6 text-2xl font-semibold tracking-tight">
                        Meta de faturamento
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed">
                        Defina quanto deseja faturar e o prazo. O card mostra
                        quanto falta com base nos leads finalizados.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <LeadMoneyField
                        id="goal-amount"
                        name="goal_amount"
                        label="Valor da meta"
                        hint="(obrigatório)"
                        value={form.data.goal_amount}
                        onChange={(value) => form.setData('goal_amount', value)}
                        disabled={processing}
                        error={form.errors.goal_amount}
                    />
                    <div className="space-y-2.5">
                        <Label id="goal-deadline-label">Prazo</Label>
                        <ToggleGroup
                            type="single"
                            value={mode}
                            onValueChange={(value) => {
                                if (value === 'days' || value === 'date') {
                                    setMode(value);
                                }
                            }}
                            disabled={processing}
                            aria-labelledby="goal-deadline-label"
                            className="grid w-full grid-cols-2 gap-1 rounded-xl border border-white/10 bg-secondary/60 p-1"
                        >
                            <ToggleGroupItem
                                value="days"
                                className="h-9 rounded-lg text-xs data-[state=on]:bg-primary data-[state=on]:text-white"
                            >
                                Em dias
                            </ToggleGroupItem>
                            <ToggleGroupItem
                                value="date"
                                className="h-9 rounded-lg text-xs data-[state=on]:bg-primary data-[state=on]:text-white"
                            >
                                Até a data
                            </ToggleGroupItem>
                        </ToggleGroup>
                        {mode === 'days' ? (
                            <>
                                <Input
                                    id="goal-deadline-days"
                                    name="deadline_days"
                                    type="number"
                                    inputMode="numeric"
                                    min={1}
                                    max={3650}
                                    step={1}
                                    required
                                    value={form.data.deadline_days}
                                    onChange={(e) =>
                                        form.setData(
                                            'deadline_days',
                                            e.target.value,
                                        )
                                    }
                                    disabled={processing}
                                    placeholder="Ex.: 30"
                                    aria-label="Prazo em dias"
                                    aria-invalid={!!form.errors.deadline_days}
                                    aria-describedby="goal-deadline-days-error"
                                    className={leadInputClass}
                                />
                                <InputError
                                    id="goal-deadline-days-error"
                                    message={form.errors.deadline_days}
                                    className="text-[#ffb4c0]"
                                />
                            </>
                        ) : (
                            <>
                                <Input
                                    id="goal-deadline-date"
                                    name="deadline_date"
                                    type="date"
                                    lang="pt-BR"
                                    required
                                    min={localCivilDate()}
                                    max="9999-12-31"
                                    value={form.data.deadline_date}
                                    onChange={(e) =>
                                        form.setData(
                                            'deadline_date',
                                            e.target.value,
                                        )
                                    }
                                    disabled={processing}
                                    aria-label="Data limite da meta"
                                    aria-invalid={!!form.errors.deadline_date}
                                    aria-describedby="goal-deadline-date-error"
                                    className={`${leadInputClass} [color-scheme:dark]`}
                                />
                                <InputError
                                    id="goal-deadline-date-error"
                                    message={form.errors.deadline_date}
                                    className="text-[#ffb4c0]"
                                />
                            </>
                        )}
                    </div>
                    <InputError
                        message={generalError || requestError}
                        role="alert"
                        className="text-[#ffb4c0]"
                    />
                    <DialogFooter className="gap-3">
                        {data.goalAmount !== null && (
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={processing}
                                onClick={remove}
                                className="min-h-11 rounded-xl text-muted-foreground hover:bg-white/[0.04] hover:text-[#ffb4c0] sm:mr-auto"
                            >
                                {removal.processing && (
                                    <LoaderCircle
                                        className="size-4 animate-spin motion-reduce:animate-none"
                                        aria-hidden="true"
                                    />
                                )}
                                Remover meta
                            </Button>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => changeOpen(false)}
                            className="min-h-11 rounded-xl border-white/10 bg-secondary/60"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing}
                            className={leadCreateClass}
                        >
                            {form.processing && (
                                <LoaderCircle
                                    className="size-4 animate-spin motion-reduce:animate-none"
                                    aria-hidden="true"
                                />
                            )}
                            {form.processing ? 'Salvando...' : 'Salvar meta'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
