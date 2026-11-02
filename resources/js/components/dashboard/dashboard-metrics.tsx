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
import {
    categoryColor,
    formatLeadDate,
    formatLeadMoney,
} from '@/lib/lead-format';
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
    const [categoryId, setCategoryId] = useState('all');
    const pending = useRef(false);
    const period = newLeads.period;
    const category = data.categories.find(
        (option) => String(option.id) === categoryId,
    );
    const categoryOptions = category
        ? [category, ...data.categories.filter((option) => option !== category)]
        : data.categories;

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
            id: 'total',
            title: category
                ? `Total de leads em "${category.name}"`
                : 'Total de leads',
            accent: category && {
                text: `"${category.name}"`,
                color: categoryColor(category.color),
            },
            value: formatCount(category ? category.count : data.totalLeads),
            label: category
                ? `${formatPercentage(category.percentage)} do total de leads`
                : 'Visão geral da base',
            icon: Users,
            truncate: true,
            background: category
                ? `linear-gradient(135deg, ${categoryColor(category.color)}59 0%, transparent 75%)`
                : undefined,
            menu: {
                heading: 'Categoria',
                ariaLabel: 'Filtrar por categoria',
                value: category ? String(category.id) : 'all',
                options: [
                    { value: 'all', label: 'Todos' },
                    ...categoryOptions.map((option) => ({
                        value: String(option.id),
                        label: option.name,
                        color: categoryColor(option.color),
                    })),
                ],
                onValueChange: setCategoryId,
            },
        },
        {
            id: 'new',
            title: 'Novos leads',
            value: formatCount(newLeads.count),
            label: periodLabel,
            icon: ScanLine,
            menu: {
                heading: 'Período de exibição',
                ariaLabel: 'Alterar período dos novos leads',
                value: period,
                options: dashboardPeriods,
                disabled: loading,
                onValueChange: (value: string) => {
                    if (
                        dashboardPeriods.some(
                            (option) => option.value === value,
                        )
                    ) {
                        selectPeriod(value as DashboardPeriod);
                    }
                },
            },
        },
        {
            id: 'goal',
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
            id: 'sold',
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
                    key={metric.id}
                    className={cn(dashboardCardClass, 'relative')}
                    style={
                        metric.background
                            ? { backgroundImage: metric.background }
                            : undefined
                    }
                >
                    <CardContent className="@container p-5 sm:p-6">
                        <div className="flex min-h-8 items-start justify-between gap-2">
                            <h2
                                className={cn(
                                    'pt-1 text-xs font-medium text-muted-foreground',
                                    metric.truncate && 'min-w-0 truncate',
                                    metric.background && 'text-foreground',
                                )}
                                title={
                                    metric.truncate ? metric.title : undefined
                                }
                            >
                                {metric.accent ? (
                                    <>
                                        Total de leads em{' '}
                                        <span
                                            style={{
                                                color: metric.accent.color,
                                            }}
                                        >
                                            {metric.accent.text}
                                        </span>
                                    </>
                                ) : (
                                    metric.title
                                )}
                            </h2>
                            {metric.menu ? (
                                <MetricSelectMenu {...metric.menu} />
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
                                metric.menu || metric.goal
                                    ? 'polite'
                                    : undefined
                            }
                        >
                            {metric.value}
                        </p>
                        <p
                            className={cn(
                                'mt-3 flex min-h-5 items-center gap-1.5 text-[11px] text-muted-foreground',
                                metric.background && 'text-foreground',
                            )}
                        >
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

function MetricSelectMenu({
    heading,
    ariaLabel,
    value,
    options,
    disabled = false,
    onValueChange,
}: {
    heading: string;
    ariaLabel: string;
    value: string;
    options: { value: string; label: string; color?: string }[];
    disabled?: boolean;
    onValueChange: (value: string) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className={dashboardEditButtonClass}
                    disabled={disabled}
                    aria-busy={disabled}
                    aria-label={ariaLabel}
                >
                    <Pencil className="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="dashboard-theme w-52 rounded-xl border-white/10 bg-popover p-2 text-foreground shadow-xl"
            >
                <DropdownMenuLabel className="px-2 py-2 text-xs text-muted-foreground">
                    {heading}
                </DropdownMenuLabel>
                <DropdownMenuSeparator className="bg-white/[0.06]" />
                <DropdownMenuRadioGroup
                    value={value}
                    onValueChange={onValueChange}
                    className="max-h-64 overflow-y-auto"
                >
                    {options.map((option) => (
                        <DropdownMenuRadioItem
                            key={option.value}
                            value={option.value}
                            className="min-h-10 cursor-pointer rounded-lg text-xs [overflow-wrap:anywhere] focus:bg-primary/20 data-[state=checked]:text-[#d3c2f7]"
                        >
                            {option.color && (
                                <span
                                    className="size-2 shrink-0 rounded-full"
                                    style={{ backgroundColor: option.color }}
                                    aria-hidden="true"
                                />
                            )}
                            {option.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
