import type { LatLngTuple } from 'leaflet';
import { ExternalLink, LoaderCircle, MapPin } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const LeadMap = lazy(() =>
    import('@/components/leads/lead-map').then((module) => ({
        default: module.LeadMap,
    })),
);

export function LeadMapDialog({
    name,
    mapsUrl,
    position,
}: {
    name: string;
    mapsUrl: string;
    position: LatLngTuple;
}) {
    const [open, setOpen] = useState(false);
    const [ready, setReady] = useState(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                setReady(false);
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`Ver localização de ${name}`}
                    title="Ver no mapa"
                    className="size-9 rounded-xl border-white/10 bg-white/[0.02] text-muted-foreground hover:bg-primary/15 hover:text-foreground"
                >
                    <MapPin className="size-3.5" aria-hidden="true" />
                </Button>
            </DialogTrigger>
            <DialogContent
                closeLabel="Fechar mapa"
                onAnimationEnd={() => setReady(open)}
                className="dashboard-theme flex max-h-[calc(100dvh-1rem)] w-[calc(100vw-1rem)] max-w-none flex-col gap-4 rounded-3xl border-white/10 bg-card p-4 text-foreground shadow-2xl sm:max-w-4xl sm:p-6"
            >
                <DialogHeader className="flex-row flex-wrap items-center justify-between gap-3 pr-8 text-left">
                    <div className="min-w-0 space-y-1">
                        <DialogTitle className="text-xl font-semibold tracking-tight [overflow-wrap:anywhere]">
                            {name}
                        </DialogTitle>
                        <DialogDescription className="text-sm">
                            Localização do lead
                        </DialogDescription>
                    </div>
                    <Button
                        asChild
                        variant="outline"
                        className="rounded-xl border-white/10 bg-white/[0.02] text-muted-foreground hover:bg-primary/15 hover:text-foreground"
                    >
                        <a
                            href={mapsUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            Abrir no Google Maps
                        </a>
                    </Button>
                </DialogHeader>
                <div className="h-[70dvh] min-h-64 overflow-hidden rounded-2xl border border-white/10 sm:h-[65vh]">
                    <Suspense
                        fallback={
                            <div className="flex h-full items-center justify-center text-muted-foreground">
                                <LoaderCircle
                                    className="size-5 animate-spin motion-reduce:animate-none"
                                    aria-hidden="true"
                                />
                            </div>
                        }
                    >
                        <LeadMap position={position} ready={ready} />
                    </Suspense>
                </div>
            </DialogContent>
        </Dialog>
    );
}
