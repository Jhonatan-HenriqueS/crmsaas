import { Link, useHttp } from '@inertiajs/react';
import { Check, ChevronDown, LoaderCircle, Tags } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { categoryColor, leadInputClass } from '@/lib/lead-format';
import { index as categoriesIndex } from '@/routes/categories';
import { categoryOptions } from '@/routes/leads';
import type { LeadCategoryOptions } from '@/types/lead';
import type { LeadCategory } from '@/types/lead-category';

export function LeadCategorySelector({
    selected,
    onSelect,
    disabled,
    invalid,
    describedBy,
}: {
    selected: LeadCategory | null;
    onSelect: (category: LeadCategory) => void;
    disabled: boolean;
    invalid: boolean;
    describedBy: string;
}) {
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState<LeadCategory[]>([]);
    const [cursor, setCursor] = useState<string | null>(null);
    const [loaded, setLoaded] = useState(false);
    const [error, setError] = useState<string>();
    const request = useHttp<Record<string, never>, LeadCategoryOptions>({});
    const generation = useRef(0);
    const loading = useRef(false);
    const color = selected ? categoryColor(selected.color) : undefined;

    async function load(next: string | null, version: number) {
        if (loading.current) return;
        loading.current = true;
        setError(undefined);
        try {
            const result = await request.get(
                categoryOptions.url({ query: next ? { cursor: next } : {} }),
            );
            if (version !== generation.current) return;
            setOptions((current) =>
                next
                    ? [
                          ...current,
                          ...result.data.filter(
                              (item) =>
                                  !current.some(
                                      (existing) => existing.id === item.id,
                                  ),
                          ),
                      ]
                    : result.data,
            );
            setCursor(result.nextCursor);
            setLoaded(true);
        } catch {
            if (version === generation.current)
                setError(
                    'Não foi possível carregar as etapas. Tente novamente.',
                );
        } finally {
            if (version === generation.current) loading.current = false;
        }
    }

    function changeOpen(next: boolean) {
        setOpen(next);
        generation.current++;
        request.cancel();
        loading.current = false;
        if (next) {
            setOptions([]);
            setLoaded(false);
            setCursor(null);
            void load(null, generation.current);
        }
    }

    return (
        <Dialog open={open} onOpenChange={changeOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    id="lead-category"
                    variant="outline"
                    disabled={disabled}
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                    className={`${leadInputClass} h-auto min-h-11 w-full justify-start gap-2.5 py-3 text-left font-normal whitespace-normal`}
                    style={
                        color
                            ? {
                                  borderColor: `${color}88`,
                                  boxShadow: `0 0 18px ${color}20`,
                              }
                            : undefined
                    }
                >
                    {selected ? (
                        <span
                            className="size-2.5 shrink-0 rounded-full"
                            style={{ backgroundColor: color }}
                            aria-hidden="true"
                        />
                    ) : (
                        <Tags
                            className="size-4 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                    )}
                    <span className="min-w-0 flex-1 [overflow-wrap:anywhere]">
                        {selected?.name ?? 'Selecionar etapa'}
                    </span>
                    <ChevronDown
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </Button>
            </DialogTrigger>
            <DialogContent
                closeLabel="Fechar seletor de etapa"
                className="dashboard-theme max-h-[calc(100dvh-2rem)] gap-5 overflow-y-auto rounded-3xl border-white/10 bg-card p-5 text-foreground shadow-2xl sm:p-7"
            >
                <DialogHeader className="text-left">
                    <DialogTitle className="pr-6 text-xl">
                        Selecionar etapa
                    </DialogTitle>
                    <DialogDescription className="leading-relaxed">
                        Escolha a categoria que representa esta oportunidade.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2" aria-busy={request.processing}>
                    {options.map((category) => (
                        <Button
                            key={category.id}
                            type="button"
                            variant="outline"
                            aria-pressed={selected?.id === category.id}
                            onClick={() => {
                                onSelect(category);
                                changeOpen(false);
                            }}
                            className="h-auto min-h-12 w-full justify-start gap-3 rounded-xl border-white/10 bg-secondary/60 px-4 py-3 text-left whitespace-normal hover:bg-accent aria-pressed:border-primary/60 aria-pressed:bg-primary/15"
                        >
                            <span
                                className="size-3 shrink-0 rounded-full border border-white/15"
                                style={{
                                    backgroundColor: categoryColor(
                                        category.color,
                                    ),
                                }}
                                aria-hidden="true"
                            />
                            <span className="min-w-0 flex-1 [overflow-wrap:anywhere]">
                                {category.name}
                            </span>
                            {selected?.id === category.id && (
                                <Check
                                    className="size-4 shrink-0 text-[#d3c2f7]"
                                    aria-hidden="true"
                                />
                            )}
                        </Button>
                    ))}
                    {request.processing && (
                        <p
                            role="status"
                            className="flex items-center justify-center gap-2 py-5 text-sm text-muted-foreground"
                        >
                            <LoaderCircle
                                className="size-4 animate-spin motion-reduce:animate-none"
                                aria-hidden="true"
                            />
                            Carregando etapas...
                        </p>
                    )}
                    {loaded && options.length === 0 && (
                        <div className="space-y-4 rounded-2xl border border-white/10 bg-secondary/40 p-5 text-sm leading-relaxed text-muted-foreground">
                            <p>
                                Nenhuma etapa cadastrada. Crie uma categoria na
                                página de Categorias antes de cadastrar seu
                                lead.
                            </p>
                            <Button
                                asChild
                                variant="outline"
                                className="rounded-xl border-primary/30 text-foreground"
                            >
                                <Link href={categoriesIndex()}>
                                    Ir para Categorias
                                </Link>
                            </Button>
                        </div>
                    )}
                    <InputError
                        message={error}
                        role="alert"
                        className="text-[#ffb4c0]"
                    />
                    {(cursor || error) && (
                        <Button
                            type="button"
                            variant="outline"
                            disabled={request.processing}
                            onClick={() =>
                                void load(cursor, generation.current)
                            }
                            className="min-h-11 rounded-xl border-white/10"
                        >
                            {error
                                ? 'Tentar novamente'
                                : 'Carregar mais etapas'}
                        </Button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
