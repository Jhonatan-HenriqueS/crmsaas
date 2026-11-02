import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    contactDeadline,
    formatLeadDate,
    leadDisplayName,
} from '@/lib/lead-format';
import { cn } from '@/lib/utils';
import { dashboardCardClass, formatCount } from './dashboard-data';
import type { DashboardUpcomingContacts } from './dashboard-data';

const urgencyClass = {
    overdue: {
        item: 'border-[#efb3be]/20 bg-[#efb3be]/[0.06]',
        label: 'text-[#efb3be]',
    },
    today: {
        item: 'border-primary/30 bg-primary/10',
        label: 'text-[#d3c2f7]',
    },
    future: {
        item: 'border-white/[0.065] bg-white/[0.025]',
        label: 'text-muted-foreground',
    },
};

export function DashboardRecords({
    data,
}: {
    data: DashboardUpcomingContacts;
}) {
    return (
        <Card
            className={cn(dashboardCardClass, 'xl:col-start-1 xl:row-start-2')}
        >
            <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 p-5 pb-1 sm:p-6 sm:pb-1">
                <CardTitle>
                    <h2 className="text-sm font-medium">Próximos contatos</h2>
                </CardTitle>
                {data.overdueCount > 0 && (
                    <span className="rounded-md border border-[#efb3be]/25 bg-[#efb3be]/10 px-2 py-1 text-[10px] text-[#efb3be]">
                        {formatCount(data.overdueCount)}{' '}
                        {data.overdueCount === 1 ? 'atrasado' : 'atrasados'}
                    </span>
                )}
            </CardHeader>
            <CardContent className="flex flex-1 flex-col p-5 pt-3 sm:p-6 sm:pt-3">
                {data.leads.length === 0 ? (
                    <div className="flex min-h-[200px] flex-col items-center justify-center gap-1.5 py-8 text-center">
                        <p className="text-sm font-medium">
                            Nenhum contato agendado
                        </p>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            Defina o próximo contato nos seus leads para montar
                            a agenda.
                        </p>
                    </div>
                ) : (
                    <div className="relative min-h-[200px] xl:flex-1">
                        <ul
                            tabIndex={0}
                            aria-label="Leads ordenados pela data do próximo contato"
                            className="max-h-[260px] [scrollbar-width:thin] space-y-2 overflow-y-auto pr-1 xl:absolute xl:inset-0 xl:max-h-none"
                        >
                            {data.leads.map((lead) => {
                                const urgency =
                                    urgencyClass[
                                        lead.next_contact_date < data.today
                                            ? 'overdue'
                                            : lead.next_contact_date ===
                                                data.today
                                              ? 'today'
                                              : 'future'
                                    ];
                                return (
                                    <li
                                        key={lead.id}
                                        className={cn(
                                            'flex min-w-0 flex-col gap-1.5 rounded-xl border px-3.5 py-3 min-[420px]:flex-row min-[420px]:items-center min-[420px]:justify-between min-[420px]:gap-3',
                                            urgency.item,
                                        )}
                                    >
                                        <span className="min-w-0 text-sm font-medium [overflow-wrap:anywhere]">
                                            {leadDisplayName(
                                                lead.company_name,
                                                lead.name,
                                            )}
                                        </span>
                                        <span className="flex shrink-0 items-baseline gap-2 text-xs min-[420px]:flex-col min-[420px]:items-end min-[420px]:gap-0.5">
                                            <time
                                                dateTime={
                                                    lead.next_contact_date
                                                }
                                                className="tabular-nums"
                                            >
                                                {formatLeadDate(
                                                    lead.next_contact_date,
                                                )}
                                            </time>
                                            <span className={urgency.label}>
                                                {contactDeadline(
                                                    lead.next_contact_date,
                                                    data.today,
                                                )}
                                            </span>
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
