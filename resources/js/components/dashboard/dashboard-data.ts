export type DashboardPeriod = 'month' | 'fortnight' | 'week';

export type DashboardData = {
    source: 'account';
    totalLeads: number;
    inProgressLeads: number;
    soldLeads: number;
    soldPercentage: number;
    soldRevenue: string;
    goalAmount: string | null;
    goalDeadline: string | null;
    goalRemaining: string | null;
    reportingTimezone: string;
    weeklyTotal: number;
    categories: {
        id: number;
        name: string;
        count: number;
        color: string;
        percentage: number;
    }[];
    weeklyIntake: { day: string; date: string; count: number }[];
};

export type DashboardNewLeads = { period: DashboardPeriod; count: number };

export const dashboardPeriods: { value: DashboardPeriod; label: string }[] = [
    { value: 'month', label: 'Último mês' },
    { value: 'fortnight', label: 'Últimas 2 semanas' },
    { value: 'week', label: 'Última semana' },
];

const countFormatter = new Intl.NumberFormat('pt-BR');
const percentFormatter = new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 1,
});

export const formatCount = (value: number): string =>
    countFormatter.format(value);
/** Percentages are rounded to one decimal place by the backend. */
export const formatPercentage = (value: number): string =>
    `${percentFormatter.format(value)}%`;

export const dashboardEditButtonClass =
    '-mt-1 -mr-1 size-9 shrink-0 rounded-full border border-white/70 bg-[#e3d8f7] text-[#4b347c] shadow-[0_0_18px_-3px_#e4d7ff55] transition-[color,background-color,border-color,box-shadow] hover:bg-[#f2ebff] hover:text-[#39245f] hover:shadow-[0_0_22px_-2px_#e4d7ff66] data-[state=open]:bg-[#f2ebff] motion-reduce:transition-none';

export const dashboardCardClass =
    'dashboard-surface min-w-0 gap-0 rounded-2xl bg-card py-0';
