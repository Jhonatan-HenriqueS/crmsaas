import { CategoryDialog } from '@/components/categories/category-dialog';
import { DeleteCategoryDialog } from '@/components/categories/delete-category-dialog';
import {
    dashboardCardClass,
    formatCount,
    formatPercentage,
} from '@/components/dashboard/dashboard-data';
import { Card, CardContent } from '@/components/ui/card';
import type { LeadCategoryMetric } from '@/types/lead-category';

export function CategoryCard({
    category,
    visibleColors,
}: {
    category: LeadCategoryMetric;
    visibleColors: string[];
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
                    <div className="flex shrink-0 gap-1.5">
                        <CategoryDialog
                            mode="edit"
                            category={category}
                            visibleColors={visibleColors}
                        />
                        <DeleteCategoryDialog category={category} />
                    </div>
                </div>
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-xl font-semibold tracking-tight tabular-nums">
                            {formatCount(category.leads_count)}
                            <span className="ml-1.5 text-xs font-normal text-muted-foreground">
                                {category.leads_count === 1 ? 'lead' : 'leads'}
                            </span>
                        </p>
                        <p className="mt-1.5 text-[11px] text-muted-foreground">
                            {formatPercentage(category.percentage)} do total de
                            leads
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
