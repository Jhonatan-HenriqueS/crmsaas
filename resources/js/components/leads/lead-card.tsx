import { Banknote, CalendarDays, Clock3, Wallet } from 'lucide-react';
import { dashboardCardClass } from '@/components/dashboard/dashboard-data';
import { CreateLeadDialog } from '@/components/leads/create-lead-dialog';
import { DeleteLeadDialog } from '@/components/leads/delete-lead-dialog';
import { CompleteLeadDialog } from '@/components/leads/complete-lead-dialog';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    categoryColor,
    contactDeadline,
    formatLeadDate,
    formatLeadMoney,
} from '@/lib/lead-format';
import type { Lead } from '@/types/lead';
import type { LeadCategory } from '@/types/lead-category';

const detailClass =
    'min-w-0 rounded-2xl border border-white/[0.065] bg-white/[0.025] p-4';

export function LeadCard({
    lead,
    today,
    page,
    completionCategory,
}: {
    lead: Lead;
    today: string;
    page: number;
    completionCategory: LeadCategory | null;
}) {
    const color = categoryColor(lead.category.color);
    const overdue =
        lead.next_contact_date !== null && lead.next_contact_date < today;
    return (
        <Card className={`${dashboardCardClass} min-w-0 rounded-3xl`}>
            <CardContent className="flex h-full flex-col gap-5 p-4 sm:p-5 xl:p-6">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 space-y-3">
                        <h2 className="text-base leading-snug font-semibold tracking-tight [overflow-wrap:anywhere]">
                            {lead.name}
                        </h2>
                        <Badge
                            className="max-w-full gap-2 rounded-full px-3 py-1.5 text-xs font-medium text-foreground"
                            style={{
                                backgroundColor: `${color}1f`,
                                borderColor: `${color}40`,
                            }}
                        >
                            <span
                                className="size-2 shrink-0 rounded-full"
                                style={{ backgroundColor: color }}
                                aria-hidden="true"
                            />
                            <span className="min-w-0 [overflow-wrap:anywhere] whitespace-normal">
                                {lead.category.name}
                            </span>
                        </Badge>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-1.5">
                        <div className="flex gap-1.5">
                            <CreateLeadDialog
                                mode="edit"
                                lead={lead}
                                page={page}
                            />
                            <DeleteLeadDialog lead={lead} page={page} />
                        </div>
                    </div>
                </div>
                {lead.description && (
                    <p className="line-clamp-2 text-sm leading-relaxed [overflow-wrap:anywhere] whitespace-pre-line text-muted-foreground">
                        {lead.description}
                    </p>
                )}
                <div className="mt-auto grid gap-3 @min-[24rem]:grid-cols-2">
                    <div className={detailClass}>
                        <p className="flex items-center gap-2 text-xs font-medium">
                            <CalendarDays
                                className="size-3.5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            Primeiro contato
                        </p>
                        <p className="mt-3 text-sm text-muted-foreground">
                            <time dateTime={lead.first_contact_date}>
                                {formatLeadDate(lead.first_contact_date)}
                            </time>
                        </p>
                    </div>
                    <div className={detailClass}>
                        <p className="flex items-center gap-2 text-xs font-medium">
                            <Clock3
                                className="size-3.5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            Próximo contato
                        </p>
                        {lead.next_contact_date && (
                            <p className="mt-3 text-base font-semibold text-[#d3c2f7]">
                                <time dateTime={lead.next_contact_date}>
                                    {formatLeadDate(lead.next_contact_date)}
                                </time>
                            </p>
                        )}
                        <p
                            className={`mt-1.5 text-xs ${overdue ? 'text-[#efb3be]' : 'text-muted-foreground'}`}
                        >
                            {contactDeadline(lead.next_contact_date, today)}
                        </p>
                    </div>
                </div>
                <div
                    className={`grid gap-3 ${lead.charged_amount !== null ? '@min-[24rem]:grid-cols-2' : ''}`}
                >
                    <div className={detailClass}>
                        <p className="flex items-center gap-2 text-xs font-medium">
                            <Wallet
                                className="size-3.5 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            Valor potencial
                        </p>
                        <p className="mt-3 text-sm font-medium [overflow-wrap:anywhere] tabular-nums">
                            {formatLeadMoney(lead.potential_amount)}
                        </p>
                    </div>
                    {lead.charged_amount !== null && (
                        <div className={detailClass}>
                            <p className="flex items-center gap-2 text-xs font-medium">
                                <Banknote
                                    className="size-3.5 shrink-0 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                Valor cobrado
                            </p>
                            <p className="mt-3 text-sm font-medium [overflow-wrap:anywhere] tabular-nums">
                                {formatLeadMoney(lead.charged_amount)}
                            </p>
                        </div>
                    )}
                </div>
                <CompleteLeadDialog
                    lead={lead}
                    category={completionCategory}
                    page={page}
                />
            </CardContent>
        </Card>
    );
}
