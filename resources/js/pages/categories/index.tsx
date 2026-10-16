import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Tags } from 'lucide-react';
import { CategoryCard } from '@/components/categories/category-card';
import { CreateCategoryDialog } from '@/components/categories/create-category-dialog';
import {
    dashboardCardClass,
    formatCount,
} from '@/components/dashboard/dashboard-data';
import { DashboardSidebar } from '@/components/dashboard/dashboard-sidebar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/categories';
import type { LeadCategoryPage } from '@/types/lead-category';

type Props = {
    categories: LeadCategoryPage;
    initialColor: string;
};

export default function Categories({ categories, initialColor }: Props) {
    const { auth } = usePage().props;
    // Synthetic examples only. These values never query leads or represent account activity.
    const examples = categories.data.map((category) => ({
        category,
        count: 12 + (category.id % 5) * 4,
    }));
    const exampleTotal = examples.reduce(
        (sum, example) => sum + example.count,
        0,
    );

    return (
        <div
            lang="pt-BR"
            className="dashboard-theme dashboard-canvas min-h-dvh bg-background text-foreground selection:bg-primary/40"
        >
            <Head title="Categorias — HING" />
            <a
                href="#categories-content"
                className="sr-only fixed top-3 left-3 z-[60] rounded-lg bg-primary px-4 py-3 text-sm text-white focus:not-sr-only"
            >
                Pular para o conteúdo
            </a>
            <DashboardSidebar user={auth.user} />
            <main
                id="categories-content"
                tabIndex={-1}
                className="min-w-0 px-4 py-7 outline-none sm:px-6 sm:py-8 lg:ml-[248px] lg:px-8 lg:py-10 2xl:ml-[264px] 2xl:px-10"
            >
                <div className="mx-auto flex w-full max-w-[1480px] min-w-0 flex-col gap-6 sm:gap-7">
                    <header className="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                        <div>
                            <p className="mb-2 text-[10px] font-medium tracking-[0.17em] text-muted-foreground uppercase">
                                Organização do seu CRM
                            </p>
                            <h1 className="text-[1.85rem] leading-tight font-semibold tracking-[-0.04em] sm:text-[2rem]">
                                Categorias
                            </h1>
                            <p className="mt-2 max-w-lg text-sm leading-relaxed text-muted-foreground">
                                Dê mais clareza às suas oportunidades com
                                categorias e cores.
                            </p>
                        </div>
                        <CreateCategoryDialog
                            initialColor={initialColor}
                            visibleColors={categories.data.map(
                                (category) => category.color,
                            )}
                        />
                    </header>
                    {categories.data.length === 0 ? (
                        <Card className={dashboardCardClass}>
                            <CardContent className="flex flex-col items-center px-5 py-16 text-center sm:py-24">
                                <span className="mb-5 flex size-14 items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 text-[#bca6ec]">
                                    <Tags
                                        className="size-6"
                                        aria-hidden="true"
                                    />
                                </span>
                                <h2 className="text-lg font-semibold tracking-tight">
                                    Suas categorias começam aqui
                                </h2>
                                <p className="mt-3 max-w-sm text-sm leading-relaxed text-muted-foreground">
                                    Nenhuma categoria foi criada. Use o botão
                                    Nova categoria para organizar suas
                                    oportunidades do seu jeito.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <section
                            aria-labelledby="registered-categories-title"
                            className="space-y-5"
                        >
                            <div className="flex flex-col gap-2 rounded-2xl border border-white/[0.055] bg-card/60 px-5 py-5 sm:px-6">
                                <h2
                                    id="registered-categories-title"
                                    className="text-lg font-semibold tracking-tight"
                                >
                                    Categorias cadastradas
                                </h2>
                                <p className="max-w-2xl text-xs leading-relaxed text-muted-foreground">
                                    Nomes e cores são reais. Indicadores de
                                    demonstração: base de{' '}
                                    {formatCount(exampleTotal)} leads fictícios
                                    nesta página.
                                </p>
                            </div>
                            <div className="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2">
                                {examples.map(({ category, count }) => (
                                    <CategoryCard
                                        key={category.id}
                                        category={category}
                                        exampleCount={count}
                                        exampleTotal={exampleTotal}
                                    />
                                ))}
                            </div>
                            {(categories.currentPage > 1 ||
                                categories.hasMorePages) && (
                                <nav
                                    aria-label="Paginação de categorias"
                                    className="flex flex-wrap items-center justify-between gap-3 pt-2"
                                >
                                    {categories.currentPage > 1 ? (
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="rounded-xl border-white/10 bg-card"
                                        >
                                            <Link
                                                href={index({
                                                    query: {
                                                        page:
                                                            categories.currentPage -
                                                            1,
                                                    },
                                                })}
                                            >
                                                <ArrowLeft
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />{' '}
                                                Anterior
                                            </Link>
                                        </Button>
                                    ) : (
                                        <span />
                                    )}
                                    <span className="text-xs text-muted-foreground">
                                        Página {categories.currentPage}
                                    </span>
                                    {categories.hasMorePages ? (
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="rounded-xl border-white/10 bg-card"
                                        >
                                            <Link
                                                href={index({
                                                    query: {
                                                        page:
                                                            categories.currentPage +
                                                            1,
                                                    },
                                                })}
                                            >
                                                Próxima{' '}
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
                    )}
                </div>
            </main>
        </div>
    );
}
