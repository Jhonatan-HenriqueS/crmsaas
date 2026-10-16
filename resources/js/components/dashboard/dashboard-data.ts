export type DashboardPeriod = 'month' | 'fortnight' | 'week';

export type DashboardData = {
    source: 'demo' | 'account';
    totalLeads: number;
    newLeads: Record<DashboardPeriod, number>;
    inProgressLeads: number;
    soldLeads: number;
    categories: { name: string; count: number; color: string }[];
    weeklyIntake: { day: string; count: number }[];
    recentRecords: {
        name: string;
        category: string;
        status: string;
        sold: boolean;
    }[];
};

export const dashboardPeriods: { value: DashboardPeriod; label: string }[] = [
    { value: 'month', label: 'Último mês' },
    { value: 'fortnight', label: 'Últimas 2 semanas' },
    { value: 'week', label: 'Última semana' },
];

/**
 * Dados fictícios e públicos, sem consultas ou registros de clientes.
 * A futura prop do Inertia deverá ser calculada no backend com CurrentAccount;
 * categorias descrevem os leads, enquanto soldLeads representa um status separado.
 */
export const dashboardDemo: DashboardData = {
    source: 'demo',
    totalLeads: 1240,
    newLeads: { week: 124, fortnight: 258, month: 462 },
    inProgressLeads: 386,
    soldLeads: 248,
    categories: [
        { name: 'Serviços', count: 496, color: 'var(--chart-1)' },
        { name: 'Tecnologia', count: 310, color: 'var(--chart-2)' },
        { name: 'Varejo', count: 248, color: 'var(--chart-3)' },
        { name: 'Outros', count: 186, color: 'var(--chart-4)' },
    ],
    weeklyIntake: [
        { day: 'Seg', count: 14 },
        { day: 'Ter', count: 18 },
        { day: 'Qua', count: 12 },
        { day: 'Qui', count: 24 },
        { day: 'Sex', count: 19 },
        { day: 'Sáb', count: 16 },
        { day: 'Dom', count: 21 },
    ],
    recentRecords: [
        {
            name: 'Marina Duarte',
            category: 'Serviços',
            status: 'Em contato',
            sold: false,
        },
        {
            name: 'Rafael Costa',
            category: 'Tecnologia',
            status: 'Negociação',
            sold: false,
        },
        {
            name: 'Beatriz Almeida',
            category: 'Varejo',
            status: 'Vendido',
            sold: true,
        },
    ],
};

const countFormatter = new Intl.NumberFormat('pt-BR');
const percentFormatter = new Intl.NumberFormat('pt-BR', {
    style: 'percent',
    maximumFractionDigits: 1,
});

export const formatCount = (value: number): string =>
    countFormatter.format(value);
export const formatShare = (count: number, total: number): string =>
    percentFormatter.format(total > 0 ? count / total : 0);

export const dashboardCardClass =
    'dashboard-surface min-w-0 gap-0 rounded-2xl bg-card py-0';
