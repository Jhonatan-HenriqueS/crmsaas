import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { dashboardCardClass } from './dashboard-data';

export function DashboardRecords() {
    return (
        <Card
            className={cn(dashboardCardClass, 'xl:col-start-1 xl:row-start-2')}
        >
            <CardHeader className="flex-row flex-wrap items-center justify-between gap-3 p-5 pb-1 sm:p-6 sm:pb-1">
                <CardTitle>
                    <h2 className="text-sm font-medium">Prévia de registros</h2>
                </CardTitle>
                <span className="rounded-md border border-white/[0.07] px-2 py-1 text-[10px] text-muted-foreground">
                    Em breve
                </span>
            </CardHeader>
            <CardContent className="p-5 pt-1 sm:p-6 sm:pt-1">
                <div className="flex min-h-[200px] items-center justify-center py-8 text-center text-xs leading-relaxed text-muted-foreground">
                    Prévia de registros disponível em breve.
                </div>
            </CardContent>
        </Card>
    );
}
