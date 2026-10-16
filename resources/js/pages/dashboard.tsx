import { Head, usePage } from '@inertiajs/react';
import {
    DashboardBarChart,
    DashboardCategoryChart,
} from '@/components/dashboard/dashboard-charts';
import { dashboardDemo } from '@/components/dashboard/dashboard-data';
import type { DashboardData } from '@/components/dashboard/dashboard-data';
import { DashboardMetrics } from '@/components/dashboard/dashboard-metrics';
import { DashboardRecords } from '@/components/dashboard/dashboard-records';
import { DashboardSidebar } from '@/components/dashboard/dashboard-sidebar';

type Props = { overview?: DashboardData };

export default function Dashboard({ overview = dashboardDemo }: Props) {
    const { auth } = usePage().props;

    return (
        <div
            lang="pt-BR"
            className="dashboard-theme dashboard-canvas min-h-dvh bg-background text-foreground selection:bg-primary/40"
        >
            <Head title="Dashboard — HING" />
            <a
                href="#dashboard-content"
                className="sr-only fixed top-3 left-3 z-[60] rounded-lg bg-primary px-4 py-3 text-sm text-white focus:not-sr-only"
            >
                Pular para o conteúdo
            </a>
            <DashboardSidebar user={auth.user} />
            <main
                id="dashboard-content"
                aria-label="Painel"
                tabIndex={-1}
                className="min-w-0 px-4 py-7 outline-none sm:px-6 sm:py-8 lg:ml-[248px] lg:px-8 lg:py-10 2xl:ml-[264px] 2xl:px-10"
            >
                <div className="mx-auto flex w-full max-w-[1480px] min-w-0 flex-col gap-6 sm:gap-7">
                    <header className="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                        <div>
                            <p className="mb-2 text-[10px] font-medium tracking-[0.17em] text-muted-foreground uppercase">
                                Seu negócio, em perspectiva
                            </p>
                            <h1 className="text-[1.85rem] leading-tight font-semibold tracking-[-0.04em] sm:text-[2rem]">
                                Dashboard
                            </h1>
                            <p className="mt-2 text-xs leading-relaxed text-muted-foreground">
                                Uma visão clara das suas oportunidades.
                            </p>
                        </div>
                        {overview.source === 'demo' && (
                            <div className="dashboard-demo self-start rounded-xl border px-3.5 py-3 sm:self-auto">
                                <p className="flex items-center gap-2 text-[11px] font-medium text-[#c8b4ee]">
                                    <span
                                        className="size-1.5 rounded-full bg-[#bca6ec]"
                                        aria-hidden="true"
                                    />
                                    Dados de demonstração
                                </p>
                                <p className="mt-1.5 text-[10px] text-muted-foreground">
                                    Os números abaixo são fictícios.
                                </p>
                            </div>
                        )}
                    </header>
                    <DashboardMetrics data={overview} />
                    <div className="grid min-w-0 grid-cols-1 items-stretch gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                        <DashboardBarChart data={overview} />
                        <DashboardCategoryChart data={overview} />
                        <DashboardRecords data={overview} />
                    </div>
                    <footer className="flex flex-wrap items-center justify-between gap-2 border-t border-white/[0.045] pt-4 text-[10px] text-muted-foreground/70">
                        <span>HING CRM</span>
                        <span>
                            {overview.source === 'demo'
                                ? 'Prévia visual · dados fictícios'
                                : 'Visão geral da sua conta'}
                        </span>
                    </footer>
                </div>
            </main>
        </div>
    );
}
