import {
    Banknote,
    CalendarDays,
    Check,
    Clock3,
    Pencil,
    Trash2,
    Wallet,
} from 'lucide-react';
import { dashboardCardClass } from '@/components/dashboard/dashboard-data';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    categoryColor,
    contactDeadline,
    formatLeadDate,
    formatLeadMoney,
} from '@/lib/lead-format';
import type { Lead } from '@/types/lead';

const detailClass =
    'min-w-0 rounded-2xl border border-white/[0.065] bg-white/[0.025] p-4';

export function LeadCard({ lead, today }: { lead: Lead; today: string }) {
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
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                disabled
                                aria-label={`Editar ${lead.name} — Em breve`}
                                title="Em breve"
                                className="size-9 rounded-xl border-white/10 bg-white/[0.02] text-muted-foreground"
                            >
                                <Pencil
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                disabled
                                aria-label={`Excluir ${lead.name} — Em breve`}
                                title="Em breve"
                                className="size-9 rounded-xl border-white/10 bg-white/[0.02] text-muted-foreground"
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
                <Button
                    type="button"
                    disabled
                    aria-label="Concluir lead — Em breve"
                    className="relative h-11 w-full rounded-xl bg-[var(--dashboard-green)] text-[#0d2820] disabled:opacity-75"
                >
                    <Check className="size-4" aria-hidden="true" />
                    <span className="absolute right-4 text-[10px] font-medium">
                        Em breve
                    </span>
                </Button>
            </CardContent>
        </Card>
    );
}
