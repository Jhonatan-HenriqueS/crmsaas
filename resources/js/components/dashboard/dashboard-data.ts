export type DashboardPeriod = 'month' | 'fortnight' | 'week';

export type DashboardData = {
    source: 'account';
    totalLeads: number;
    inProgressLeads: number;
    soldLeads: number;
    soldPercentage: number;
    soldRevenue: string;
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

export const dashboardCardClass =
    'dashboard-surface min-w-0 gap-0 rounded-2xl bg-card py-0';
