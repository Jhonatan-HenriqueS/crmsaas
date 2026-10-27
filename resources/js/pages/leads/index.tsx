import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    dashboardCardClass,
    formatCount,
} from '@/components/dashboard/dashboard-data';
import { DashboardSidebar } from '@/components/dashboard/dashboard-sidebar';
import { CreateLeadDialog } from '@/components/leads/create-lead-dialog';
import { LeadCard } from '@/components/leads/lead-card';
import { LeadCategoryFilter } from '@/components/leads/lead-category-filter';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { localCivilDate } from '@/lib/lead-format';
import { index } from '@/routes/leads';
import type { LeadPage } from '@/types/lead';
import type { LeadCategory } from '@/types/lead-category';

export default function Leads({
    leads,
    completionCategory,
    selectedCategory,
}: {
    leads: LeadPage;
    selectedCategory: LeadCategory | null;
    completionCategory: LeadCategory | null;
}) {
    const { auth } = usePage().props;
    const [today, setToday] = useState(() => localCivilDate());
    useEffect(() => {
        // Refresh on focus and at local midnight, including changes across DST.
        let timer: ReturnType<typeof setTimeout>;
        const refresh = () => {
            setToday(localCivilDate());
            clearTimeout(timer);
            const midnight = new Date();
            midnight.setHours(24, 0, 0, 0);
            timer = setTimeout(refresh, midnight.getTime() - Date.now() + 100);
        };
        refresh();
        window.addEventListener('focus', refresh);
        return () => {
            clearTimeout(timer);
            window.removeEventListener('focus', refresh);
        };
    }, []);

    return (
        <div
            lang="pt-BR"
            className="dashboard-theme dashboard-canvas min-h-dvh bg-background text-foreground selection:bg-primary/40"
        >
            <Head title="Leads — HING" />
            <a
                href="#leads-content"
                className="sr-only fixed top-3 left-3 z-[60] rounded-lg bg-primary px-4 py-3 text-sm text-white focus:not-sr-only"
            >
                Pular para o conteúdo
            </a>
            <DashboardSidebar user={auth.user} />
            <main
                id="leads-content"
                tabIndex={-1}
                className="min-w-0 px-3 py-5 outline-none sm:px-6 sm:py-8 lg:ml-[248px] lg:px-8 lg:py-10 2xl:ml-[264px] 2xl:px-10"
            >
                <section
                    aria-labelledby="leads-title"
                    className="mx-auto w-full max-w-[1480px] min-w-0 space-y-7 rounded-[2rem] border border-white/[0.055] bg-card/15 p-4 sm:space-y-9 sm:p-7 xl:p-8"
                >
                    <header className="space-y-6">
                        <div>
                            <p className="mb-2 text-[10px] font-medium tracking-[0.2em] text-muted-foreground uppercase">
                                Lista de leads
                            </p>
                            <h1
                                id="leads-title"
                                className="text-[1.85rem] leading-tight font-semibold tracking-[-0.04em] sm:text-[2rem]"
                            >
                                Leads e oportunidades
                            </h1>
                            <p className="mt-3 max-w-2xl text-sm leading-relaxed text-muted-foreground">
                                Organize seus contatos, acompanhe os próximos
                                retornos e visualize o potencial de cada
                                oportunidade.
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center justify-end gap-3">
                            <div
                                className="flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-white/[0.07] bg-card px-3 py-2 text-xs text-muted-foreground sm:flex-none sm:px-4 sm:text-sm"
                                aria-live="polite"
                            >
                                <Users
                                    className="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>
                                    {formatCount(leads.total)}{' '}
                                    {selectedCategory
                                        ? leads.total === 1
                                            ? 'lead nesta categoria'
                                            : 'leads nesta categoria'
                                        : leads.total === 1
                                          ? 'lead cadastrado'
                                          : 'leads cadastrados'}
                                </span>
                            </div>
                            <LeadCategoryFilter selected={selectedCategory} />
                            <CreateLeadDialog mode="create" />
                        </div>
                    </header>
                    {leads.data.length === 0 ? (
                        <Card className={`${dashboardCardClass} rounded-3xl`}>
                            <CardContent className="flex flex-col items-center px-5 py-16 text-center sm:py-24">
                                <span className="mb-5 flex size-14 items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 text-[#bca6ec]">
                                    <Users
                                        className="size-6"
                                        aria-hidden="true"
                                    />
                                </span>
                                <h2 className="text-lg font-semibold tracking-tight">
                                    {selectedCategory
                                        ? 'Nenhum lead nesta categoria'
                                        : 'Nenhum lead cadastrado'}
                                </h2>
                                <p className="mt-3 max-w-md text-sm leading-relaxed text-muted-foreground">
                                    {selectedCategory
                                        ? 'Não encontramos leads vinculados à categoria selecionada.'
                                        : 'Comece criando sua primeira oportunidade.'}
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <div className="grid min-w-0 grid-cols-1 gap-5 min-[1200px]:grid-cols-2">
                            {leads.data.map((lead) => (
                                <div
                                    key={lead.id}
                                    className="@container flex min-w-0 [&>div]:w-full"
                                >
                                    <LeadCard
                                        lead={lead}
                                        today={today}
                                        page={leads.currentPage}
                                        completionCategory={completionCategory}
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                    {(leads.currentPage > 1 || leads.hasMorePages) && (
                        <nav
                            aria-label="Paginação de leads"
                            className="flex flex-wrap items-center justify-between gap-3"
                        >
                            {leads.currentPage > 1 ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="rounded-xl border-white/10 bg-card"
                                >
                                    <Link
                                        href={index({
                                            query: {
                                                category: selectedCategory?.id,
                                                page: leads.currentPage - 1,
                                            },
                                        })}
                                    >
                                        <ArrowLeft
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Anterior
                                    </Link>
                                </Button>
                            ) : (
                                <span />
                            )}
                            <span className="text-xs text-muted-foreground">
                                Página {leads.currentPage}
                            </span>
                            {leads.hasMorePages ? (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="rounded-xl border-white/10 bg-card"
                                >
                                    <Link
                                        href={index({
                                            query: {
                                                category: selectedCategory?.id,
                                                page: leads.currentPage + 1,
                                            },
                                        })}
                                    >
                                        Próxima
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </Button>
                            ) : (
                                <span />
                            )}
                        </nav>
                    )}
                </section>
            </main>
        </div>
    );
}
