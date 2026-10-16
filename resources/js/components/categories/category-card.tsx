import { Pencil, Trash2 } from 'lucide-react';
import {
    dashboardCardClass,
    formatCount,
    formatShare,
} from '@/components/dashboard/dashboard-data';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { LeadCategory } from '@/types/lead-category';

export function CategoryCard({
    category,
    exampleCount,
    exampleTotal,
}: {
    category: LeadCategory;
    exampleCount: number;
    exampleTotal: number;
}) {
    return (
        <Card
            className={`${dashboardCardClass} category-card relative transition-[background-color,box-shadow,scale] duration-200 hover:z-10 motion-safe:hover:scale-[1.02] motion-reduce:transition-none`}
        >
            <CardContent className="flex h-full flex-col gap-3 p-5 sm:p-6">
                <div className="flex min-w-0 items-start justify-between gap-3">
                    <div className="flex min-w-0 items-start gap-3">
                        <span
                            className="mt-1 size-3.5 shrink-0 rounded-full border border-white/15"
                            style={{ backgroundColor: category.color }}
                            aria-hidden="true"
                        />
                        <div className="min-w-0">
                            <h2 className="text-base leading-snug font-semibold tracking-tight [overflow-wrap:anywhere]">
                                {category.name}
                            </h2>
                            <p className="mt-2 font-mono text-[11px] text-muted-foreground">
                                {category.color}
                            </p>
                        </div>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-2">
                        <div className="flex gap-1.5">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled
                                aria-label={`Editar ${category.name} — em breve`}
                                title="Editar categoria — em breve"
                                className="size-9 rounded-xl border border-white/10 bg-white/[0.025] text-muted-foreground disabled:opacity-50"
                            >
                                <Pencil
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                disabled
                                aria-label={`Excluir ${category.name} — em breve`}
                                title="Excluir categoria — em breve"
                                className="size-9 rounded-xl border border-white/10 bg-white/[0.025] text-[#e5a6b2] disabled:opacity-50"
                            >
                                <Trash2
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </Button>
                        </div>
                        <span className="text-[10px] text-muted-foreground">
                            Em breve
                        </span>
                    </div>
                </div>
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-xl font-semibold tracking-tight tabular-nums">
                            {formatCount(exampleCount)}
                            <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                                leads fictícios
                            </span>
                        </p>
                        <p className="mt-1.5 text-[11px] text-muted-foreground">
                            {formatShare(exampleCount, exampleTotal)} da base
                            fictícia desta página
                        </p>
                    </div>
                    <span className="rounded-full border border-primary/25 bg-primary/10 px-2.5 py-1 text-[10px] font-medium text-[#c8b4ee]">
                        Demonstração
                    </span>
                </div>
            </CardContent>
        </Card>
    );
}
