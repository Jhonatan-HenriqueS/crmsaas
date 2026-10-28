import { router } from '@inertiajs/react';
import {
    Check,
    CircleDollarSign,
    Pencil,
    ScanLine,
    Target,
    Users,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { DashboardGoalDialog } from '@/components/dashboard/dashboard-goal-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatLeadDate, formatLeadMoney } from '@/lib/lead-format';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    dashboardCardClass,
    dashboardEditButtonClass,
    dashboardPeriods,
    formatCount,
    formatPercentage,
} from './dashboard-data';
import type {
    DashboardData,
    DashboardNewLeads,
    DashboardPeriod,
} from './dashboard-data';

export function DashboardMetrics({
    data,
    newLeads,
}: {
    data: DashboardData;
    newLeads: DashboardNewLeads;
}) {
    const [loading, setLoading] = useState(false);
    const pending = useRef(false);
    const period = newLeads.period;

    function selectPeriod(value: DashboardPeriod) {
        if (pending.current || value === period) return;
        pending.current = true;
        setLoading(true);
        router.get(
            dashboard.url({ query: { period: value } }),
            {},
            {
                only: ['newLeads'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onFinish: () => {
                    pending.current = false;
                    setLoading(false);
                },
            },
        );
    }
    const periodLabel = dashboardPeriods.find(
        (option) => option.value === period,
    )?.label;
    const metrics = [
        {
            title: 'Total de leads',
            value: formatCount(data.totalLeads),
            label: 'Visão geral da base',
            icon: Users,
        },
        {
            title: 'Novos leads',
            value: formatCount(newLeads.count),
            label: periodLabel,
            icon: ScanLine,
            editable: true,
        },
        {
            title: 'Meta',
            value:
                data.goalRemaining === null
                    ? 'Sem meta'
                    : formatLeadMoney(data.goalRemaining),
            label:
                data.goalRemaining === null
                    ? 'Nenhuma meta definida · use o botão de editar'
                    : `Meta: ${formatLeadMoney(data.goalAmount)}${data.goalDeadline ? ` · até ${formatLeadDate(data.goalDeadline)}` : ''}`,
            icon: Target,
            goal: true,
            fit: true,
        },
        {
            title: 'Leads vendidos',
            value: formatLeadMoney(data.soldRevenue),
            label: `${formatPercentage(data.soldPercentage)} do total de leads · ${formatCount(data.soldLeads)} ${data.soldLeads === 1 ? 'lead vendido' : 'leads vendidos'}`,
            icon: CircleDollarSign,
            green: true,
            fit: true,
        },
    ];

    return (
        <section
            aria-label="Indicadores do dashboard"
            className="grid min-w-0 grid-cols-1 gap-3 min-[480px]:grid-cols-2 sm:gap-4 xl:grid-cols-4"
        >
            {metrics.map((metric) => (
                <Card
                    key={metric.title}
                    className={cn(dashboardCardClass, 'relative')}
                >
                    <CardContent className="@container p-5 sm:p-6">
                        <div className="flex min-h-8 items-start justify-between gap-2">
                            <h2 className="pt-1 text-xs font-medium text-muted-foreground">
                                {metric.title}
                            </h2>
                            {metric.editable ? (
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className={dashboardEditButtonClass}
                                            disabled={loading}
                                            aria-busy={loading}
                                            aria-label="Alterar período dos novos leads"
                                        >
                                            <Pencil
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        sideOffset={8}
                                        className="dashboard-theme w-52 rounded-xl border-white/10 bg-popover p-2 text-foreground shadow-xl"
                                    >
                                        <DropdownMenuLabel className="px-2 py-2 text-xs text-muted-foreground">
                                            Período de exibição
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator className="bg-white/[0.06]" />
                                        <DropdownMenuRadioGroup
                                            value={period}
                                            onValueChange={(value) => {
                                                if (
                                                    dashboardPeriods.some(
                                                        (option) =>
                                                            option.value ===
                                                            value,
                                                    )
                                                ) {
                                                    selectPeriod(
                                                        value as DashboardPeriod,
                                                    );
                                                }
                                            }}
                                        >
                                            {dashboardPeriods.map((option) => (
                                                <DropdownMenuRadioItem
                                                    key={option.value}
                                                    value={option.value}
                                                    className="min-h-10 cursor-pointer rounded-lg text-xs focus:bg-primary/20 data-[state=checked]:text-[#d3c2f7]"
                                                >
                                                    {option.label}
                                                </DropdownMenuRadioItem>
                                            ))}
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            ) : metric.goal ? (
                                <DashboardGoalDialog data={data} />
                            ) : (
                                <metric.icon
                                    className={cn(
                                        'mt-1 size-[17px] shrink-0 text-muted-foreground/60',
                                        metric.green &&
                                            'text-[var(--dashboard-green)]',
                                    )}
                                    aria-hidden="true"
                                />
                            )}
                        </div>
                        <p
                            className={cn(
                                'mt-2 text-[2.1rem] leading-none font-semibold tracking-[-0.045em] tabular-nums',
                                metric.fit &&
                                    'text-[clamp(1.25rem,11.5cqw,2.1rem)] [overflow-wrap:anywhere]',
                                metric.green && 'text-[var(--dashboard-green)]',
                            )}
                            aria-live={
                                metric.editable || metric.goal
                                    ? 'polite'
                                    : undefined
                            }
                        >
                            {metric.value}
                        </p>
                        <p className="mt-3 flex min-h-5 items-center gap-1.5 text-[11px] text-muted-foreground">
                            {metric.green ? (
                                <Check
                                    className="size-3.5 text-[var(--dashboard-green)]"
                                    aria-hidden="true"
                                />
                            ) : (
                                <span
                                    className="size-1 rounded-full bg-primary/80"
                                    aria-hidden="true"
                                />
                            )}
                            {metric.label}
                        </p>
                    </CardContent>
                </Card>
            ))}
        </section>
    );
}
