import { ArrowUpRight, CheckCheck, ChevronDown, ChevronUp } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import {
    dashboardCardClass,
    formatCount,
    formatPercentage,
} from './dashboard-data';
import type { DashboardData } from './dashboard-data';

export function DashboardBarChart({ data }: { data: DashboardData }) {
    const weeklyTotal = data.weeklyTotal;
    const chartData = data.weeklyIntake.map((day, index) => ({
        ...day,
        fill:
            index === data.weeklyIntake.length - 1
                ? 'var(--dashboard-green)'
                : 'var(--primary)',
    }));

    return (
        <Card
            className={cn(dashboardCardClass, 'xl:col-start-1 xl:row-start-1')}
        >
            <CardHeader className="gap-4 p-5 pb-0 sm:p-6 sm:pb-0">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <CardTitle>
                        <h2 id="intake-title" className="text-sm font-medium">
                            Entrada de leads
                        </h2>
                    </CardTitle>
                    <span className="rounded-md border border-white/[0.07] bg-white/[0.025] px-2.5 py-1 text-[10px] text-muted-foreground">
                        Semana atual
                    </span>
                </div>
                <div className="flex items-baseline gap-3">
                    <p className="text-3xl font-semibold tracking-[-0.04em] tabular-nums">
                        {formatCount(weeklyTotal)}
                    </p>
                    <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
                        <ArrowUpRight
                            className="size-3.5 text-[var(--dashboard-green)]"
                            aria-hidden="true"
                        />
                        leads no período
                    </span>
                </div>
            </CardHeader>
            <CardContent className="min-w-0 px-3 pt-6 pb-4 sm:px-5 sm:pb-5">
                <div
                    className="h-[230px] min-w-0 sm:h-[242px]"
                    role="group"
                    aria-labelledby="intake-title"
                >
                    <ResponsiveContainer
                        width="100%"
                        height="100%"
                        minWidth={0}
                    >
                        <BarChart
                            data={chartData}
                            margin={{ top: 8, right: 8, bottom: 0, left: -12 }}
                            accessibilityLayer
                        >
                            <CartesianGrid
                                vertical={false}
                                stroke="var(--dashboard-grid)"
                                strokeDasharray="4 5"
                            />
                            <XAxis
                                dataKey="day"
                                axisLine={false}
                                tickLine={false}
                                tick={{
                                    fill: 'var(--muted-foreground)',
                                    fontSize: 10,
                                }}
                                tickMargin={12}
                                interval={0}
                            />
                            <YAxis
                                axisLine={false}
                                tickLine={false}
                                tick={{
                                    fill: 'var(--muted-foreground)',
                                    fontSize: 10,
                                }}
                                tickMargin={8}
                                width={40}
                                allowDecimals={false}
                            />
                            <Tooltip
                                cursor={{
                                    fill: 'var(--dashboard-grid)',
                                    radius: 6,
                                }}
                                isAnimationActive={false}
                                content={({ active, payload, label }) =>
                                    active && payload?.length ? (
                                        <div className="rounded-xl border border-white/10 bg-popover px-3.5 py-3 text-xs shadow-xl">
                                            <p className="mb-1 text-muted-foreground">
                                                {label} · Semana atual
                                            </p>
                                            <p className="font-medium text-foreground">
                                                {formatCount(
                                                    Number(payload[0].value),
                                                )}{' '}
                                                novos leads
                                            </p>
                                        </div>
                                    ) : null
                                }
                            />
                            <Bar
                                dataKey="count"
                                name="Novos leads"
                                radius={[5, 5, 0, 0]}
                                maxBarSize={30}
                                isAnimationActive={false}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
                <p className="mt-3 text-center text-[10px] text-muted-foreground/75">
                    Semana atual · segunda a domingo · {data.reportingTimezone}
                </p>
            </CardContent>
        </Card>
    );
}

export function DashboardCategoryChart({ data }: { data: DashboardData }) {
    const categoryListRef = useRef<HTMLUListElement>(null);
    const [hasMoreCategoriesAbove, setHasMoreCategoriesAbove] = useState(false);
    const [hasMoreCategoriesBelow, setHasMoreCategoriesBelow] = useState(false);

    useEffect(() => {
        const list = categoryListRef.current;

        if (!list) {
            return;
        }

        const updateScrollIndicator = () => {
            setHasMoreCategoriesAbove(list.scrollTop > 1);
            setHasMoreCategoriesBelow(
                list.scrollHeight - list.clientHeight - list.scrollTop > 1,
            );
        };
        const frame = requestAnimationFrame(updateScrollIndicator);
        const observer = new ResizeObserver(updateScrollIndicator);

        observer.observe(list);
        for (const category of list.children) {
            observer.observe(category);
        }
        list.addEventListener('scroll', updateScrollIndicator, {
            passive: true,
        });

        return () => {
            cancelAnimationFrame(frame);
            observer.disconnect();
            list.removeEventListener('scroll', updateScrollIndicator);
        };
    }, [data.categories]);

    const scrollToCategory = (position: 'first' | 'last') => {
        const list = categoryListRef.current;

        if (!list) {
            return;
        }

        list.focus({ preventScroll: true });
        list.scrollTo({
            top: position === 'first' ? 0 : list.scrollHeight,
            behavior: window.matchMedia('(prefers-reduced-motion: reduce)')
                .matches
                ? 'instant'
                : 'smooth',
        });
    };

    const chartData = data.categories
        .filter((category) => category.count > 0)
        .map((category) => ({
            ...category,
            fill: category.color,
        }));

    return (
        <Card
            className={cn(
                dashboardCardClass,
                'xl:col-start-2 xl:row-span-2 xl:row-start-1',
            )}
        >
            <CardHeader className="gap-2 p-5 pb-0 sm:p-6 sm:pb-0">
                <CardTitle>
                    <h2 id="categories-title" className="text-sm font-medium">
                        Leads por categoria
                    </h2>
                </CardTitle>
                <p className="text-xs text-muted-foreground">
                    Cada oportunidade, no seu lugar.
                </p>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col p-5 pt-0 sm:p-6 sm:pt-0">
                <div
                    className="relative my-3 h-[250px] min-w-0 sm:h-[270px]"
                    role="group"
                    aria-labelledby="categories-title"
                >
                    {data.totalLeads > 0 && (
                        <ResponsiveContainer
                            width="100%"
                            height="100%"
                            minWidth={0}
                        >
                            <PieChart accessibilityLayer>
                                <Pie
                                    data={chartData}
                                    dataKey="count"
                                    nameKey="name"
                                    innerRadius="66%"
                                    outerRadius="86%"
                                    startAngle={90}
                                    endAngle={-270}
                                    paddingAngle={3}
                                    cornerRadius={4}
                                    stroke="none"
                                    isAnimationActive={false}
                                />
                                <Tooltip
                                    isAnimationActive={false}
                                    content={({ active, payload }) => {
                                        const category = payload?.[0]
                                            ?.payload as
                                            | DashboardData['categories'][number]
                                            | undefined;
                                        return active && category ? (
                                            <div className="rounded-xl border border-white/10 bg-popover px-3.5 py-3 text-xs shadow-xl">
                                                <p className="mb-1 font-medium text-foreground">
                                                    {category.name}
                                                </p>
                                                <p className="text-muted-foreground">
                                                    {formatCount(
                                                        category.count,
                                                    )}{' '}
                                                    leads ·{' '}
                                                    {formatPercentage(
                                                        category.percentage,
                                                    )}{' '}
                                                    do total
                                                </p>
                                            </div>
                                        ) : null;
                                    }}
                                />
                            </PieChart>
                        </ResponsiveContainer>
                    )}
                    <div
                        className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"
                        aria-hidden={data.totalLeads > 0}
                    >
                        <span className="mb-1 text-[10px] tracking-[0.12em] text-muted-foreground uppercase">
                            Total de leads
                        </span>
                        <span className="text-3xl font-semibold tracking-[-0.04em] tabular-nums">
                            {formatCount(data.totalLeads)}
                        </span>
                        <span className="mt-2 h-0.5 w-6 rounded-full bg-primary" />
                        {data.totalLeads === 0 && (
                            <span className="mt-3 text-xs text-muted-foreground">
                                Nenhum lead cadastrado
                            </span>
                        )}
                    </div>
                </div>
                <div className="mb-3 flex items-center justify-between gap-3 border-b border-white/[0.06] pb-3 text-[10px] text-muted-foreground">
                    <span>Categoria</span>
                    <span>Quantidade / %</span>
                </div>
                <div className="flex h-8 shrink-0 items-center justify-center">
                    {hasMoreCategoriesAbove && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-10 text-muted-foreground hover:text-foreground"
                            aria-label="Ir para a primeira categoria"
                            onClick={() => scrollToCategory('first')}
                        >
                            <ChevronUp className="size-4" aria-hidden="true" />
                        </Button>
                    )}
                </div>
                <ul
                    ref={categoryListRef}
                    className="max-h-40 [scrollbar-width:none] space-y-4 overflow-y-auto pr-1 [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
                    tabIndex={0}
                    aria-label="Quantidades e percentuais por categoria"
                >
                    {data.categories.length === 0 && (
                        <li className="text-xs text-muted-foreground">
                            Nenhuma categoria cadastrada.
                        </li>
                    )}
                    {data.categories.map((category) => (
                        <li
                            key={category.id}
                            className="flex min-w-0 items-center gap-2.5 text-xs"
                        >
                            <span
                                className="size-2 shrink-0 rounded-full"
                                style={{ backgroundColor: category.color }}
                                aria-hidden="true"
                            />
                            <span className="min-w-0 flex-1 [overflow-wrap:anywhere] text-muted-foreground">
                                {category.name}
                            </span>
                            <span className="font-medium tabular-nums">
                                {formatCount(category.count)}
                            </span>
                            <span className="w-10 text-right text-muted-foreground tabular-nums">
                                {formatPercentage(category.percentage)}
                            </span>
                        </li>
                    ))}
                </ul>
                <div className="flex h-8 shrink-0 items-center justify-center">
                    {hasMoreCategoriesBelow && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-10 text-muted-foreground hover:text-foreground"
                            aria-label="Ir para a última categoria"
                            onClick={() => scrollToCategory('last')}
                        >
                            <ChevronDown
                                className="size-4"
                                aria-hidden="true"
                            />
                        </Button>
                    )}
                </div>
                <div className="dashboard-sales-highlight mt-7 rounded-xl p-4 xl:mt-auto xl:translate-y-1">
                    <div className="mb-3 flex items-center gap-2 text-xs font-medium text-[var(--sales-accent)]">
                        <CheckCheck
                            className="size-4 text-[var(--dashboard-green)]"
                            aria-hidden="true"
                        />
                        Leads vendidos
                    </div>
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <span className="text-[1.75rem] leading-none font-semibold tracking-tight tabular-nums">
                            {formatCount(data.soldLeads)}
                        </span>
                        <span className="text-xs font-medium text-[var(--sales-accent)]">
                            {formatPercentage(data.soldPercentage)} do total
                            geral
                        </span>
                    </div>
                    <p className="mt-2 text-[11px] leading-relaxed text-[var(--sales-muted)]">
                        Vendas concluídas, entregues e pagas, conforme sua
                        confirmação.
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}
