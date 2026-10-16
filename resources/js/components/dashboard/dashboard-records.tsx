import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { dashboardCardClass } from './dashboard-data';
import type { DashboardData } from './dashboard-data';

export function DashboardRecords({ data }: { data: DashboardData }) {
    const initials = useInitials();

    return (
        <Card
            className={cn(dashboardCardClass, 'xl:col-start-1 xl:row-start-2')}
        >
            <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 p-5 pb-1 sm:p-6 sm:pb-1">
                <CardTitle>
                    <h2 className="text-sm font-medium">Prévia de registros</h2>
                </CardTitle>
                <span className="rounded-md border border-white/[0.07] px-2 py-1 text-[10px] text-muted-foreground">
                    {data.source === 'demo'
                        ? 'Exemplos fictícios'
                        : 'Somente leitura'}
                </span>
            </CardHeader>
            <CardContent className="p-5 pt-1 sm:p-6 sm:pt-1">
                <ul
                    className="divide-y divide-white/[0.05]"
                    aria-label="Prévia de registros, sem ações disponíveis"
                >
                    {data.recentRecords.map((record) => (
                        <li
                            key={record.name}
                            className="flex min-w-0 items-center gap-3 py-4 last:pb-0"
                        >
                            <Avatar className="size-9 border border-white/[0.06]">
                                <AvatarFallback className="bg-white/[0.035] text-[10px] font-medium text-muted-foreground">
                                    {initials(record.name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1">
                                <p
                                    className="truncate text-xs font-medium"
                                    title={record.name}
                                >
                                    {record.name}
                                </p>
                                <p className="mt-1 text-[10px] text-muted-foreground">
                                    {record.category}
                                </p>
                            </div>
                            <Badge
                                variant="outline"
                                className={cn(
                                    'max-w-[45%] shrink-0 rounded-md border-white/[0.06] bg-white/[0.02] px-2 py-1 text-[9px] font-normal text-muted-foreground',
                                    record.sold &&
                                        'border-[var(--dashboard-green)]/10 bg-[var(--dashboard-green)]/5 text-[var(--dashboard-green)]',
                                )}
                            >
                                {record.status}
                            </Badge>
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
