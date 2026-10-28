import { useForm, usePage } from '@inertiajs/react';
import { ExternalLink, LoaderCircle, MapPin } from 'lucide-react';
import { lazy, Suspense, useRef, useState } from 'react';
import { LeadMapsUrlField } from '@/components/leads/lead-maps-url-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { mapsLinkFrom } from '@/lib/lead-format';
import { location } from '@/routes/leads';
import type { Lead } from '@/types/lead';
import type { LeadCategory } from '@/types/lead-category';

const LeadMap = lazy(() =>
    import('@/components/leads/lead-map').then((module) => ({
        default: module.LeadMap,
    })),
);

export function LeadMapDialog({ lead, page }: { lead: Lead; page: number }) {
    const { selectedCategory } = usePage<{
        selectedCategory?: LeadCategory | null;
    }>().props;
    const [open, setOpen] = useState(false);
    const [ready, setReady] = useState(false);
    const lastSent = useRef<string>(undefined);
    const form = useForm({ maps_url: '' });
    const located =
        lead.maps_url !== null &&
        lead.latitude !== null &&
        lead.longitude !== null;
    const fieldId = `lead-${lead.id}-maps-url`;

    function save(value: string) {
        const link = value.trim();
        if (form.processing || link === '' || link === lastSent.current) return;
        lastSent.current = link;
        form.transform(() => ({ maps_url: link }));
        form.submit(
            location(lead.id, {
                query: { page, category: selectedCategory?.id },
            }),
            {
                preserveScroll: true,
                only: ['leads'],
                onHttpException: () => {
                    lastSent.current = undefined;
                    form.setError(
                        'maps_url',
                        'Não foi possível salvar o link. Atualize a página e verifique sua sessão.',
                    );
                    return false;
                },
                onNetworkError: () => {
                    lastSent.current = undefined;
                    form.setError(
                        'maps_url',
                        'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.',
                    );
                    return false;
                },
            },
        );
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (form.processing) return;
                if (next) {
                    form.reset();
                    form.clearErrors();
                    lastSent.current = undefined;
                }
                setOpen(next);
                setReady(false);
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    aria-label={`Ver localização de ${lead.name}`}
                    title="Ver no mapa"
                    className="size-9 rounded-xl border-white/10 bg-white/[0.02] text-muted-foreground hover:bg-primary/15 hover:text-foreground"
                >
                    <MapPin className="size-3.5" aria-hidden="true" />
                </Button>
            </DialogTrigger>
            <DialogContent
                closeLabel="Fechar mapa"
                closeDisabled={form.processing}
                aria-busy={form.processing}
                onAnimationEnd={() => setReady(open)}
                className="dashboard-theme flex max-h-[calc(100dvh-1rem)] w-[calc(100vw-1rem)] max-w-none flex-col gap-4 rounded-3xl border-white/10 bg-card p-4 text-foreground shadow-2xl sm:max-w-4xl sm:p-6"
            >
                <DialogHeader className="flex-row flex-wrap items-center justify-between gap-3 pr-8 text-left">
                    <div className="min-w-0 space-y-1">
                        <DialogTitle className="text-xl font-semibold tracking-tight [overflow-wrap:anywhere]">
                            {lead.name}
                        </DialogTitle>
                        <DialogDescription className="text-sm">
                            Localização do lead
                        </DialogDescription>
                    </div>
                    {located && (
                        <Button
                            asChild
                            variant="outline"
                            className="rounded-xl border-white/10 bg-white/[0.02] text-muted-foreground hover:bg-primary/15 hover:text-foreground"
                        >
                            <a
                                href={lead.maps_url ?? undefined}
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
                    )}
                </DialogHeader>
                <div className="h-[70dvh] min-h-64 overflow-hidden rounded-2xl border border-white/10 sm:h-[65vh]">
                    {located ? (
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
                            <LeadMap
                                position={[
                                    Number(lead.latitude),
                                    Number(lead.longitude),
                                ]}
                                ready={ready}
                            />
                        </Suspense>
                    ) : (
                        <div className="flex h-full items-center justify-center overflow-y-auto p-4">
                            <div className="w-full max-w-md space-y-4 text-center">
                                <span className="mx-auto flex size-12 items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 text-[#bca6ec]">
                                    {form.processing ? (
                                        <LoaderCircle
                                            className="size-5 animate-spin motion-reduce:animate-none"
                                            aria-label="Salvando link"
                                        />
                                    ) : (
                                        <MapPin
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    )}
                                </span>
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Este lead ainda não tem localização. Abra o
                                    local no Google Maps, toque em Compartilhar
                                    e cole o link abaixo.
                                </p>
                                <div className="space-y-2.5 text-left">
                                    <Label htmlFor={fieldId}>
                                        Link do Google Maps
                                    </Label>
                                    <LeadMapsUrlField
                                        id={fieldId}
                                        value={form.data.maps_url}
                                        onChange={(value) =>
                                            form.setData('maps_url', value)
                                        }
                                        onPasteLink={(link) => {
                                            form.setData('maps_url', link);
                                            save(link);
                                        }}
                                        onPaste={(event) => {
                                            event.preventDefault();
                                            const link = mapsLinkFrom(
                                                event.clipboardData.getData(
                                                    'text',
                                                ),
                                            );
                                            form.setData('maps_url', link);
                                            save(link);
                                        }}
                                        onKeyDown={(event) => {
                                            if (event.key !== 'Enter') return;
                                            event.preventDefault();
                                            save(form.data.maps_url);
                                        }}
                                        onBlur={() => save(form.data.maps_url)}
                                        disabled={form.processing}
                                        error={
                                            form.errors.maps_url ??
                                            Object.values(form.errors)[0]
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
