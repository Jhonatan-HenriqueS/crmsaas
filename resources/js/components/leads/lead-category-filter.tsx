import { router } from '@inertiajs/react';
import { ChevronDown, SlidersHorizontal } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLeadCategoryOptions } from '@/hooks/use-lead-category-options';
import { categoryColor } from '@/lib/lead-format';
import { index } from '@/routes/leads';
import type { LeadCategory } from '@/types/lead-category';

export function LeadCategoryFilter({
    selected,
}: {
    selected: LeadCategory | null;
}) {
    const [open, setOpen] = useState(false);
    const [filtering, setFiltering] = useState(false);
    const trigger = useRef<HTMLButtonElement>(null);
    const pending = useRef(false);
    const { options, cursor, error, processing, changeOpen, loadMore } =
        useLeadCategoryOptions();
    const visibleOptions =
        selected && !options.some((category) => category.id === selected.id)
            ? [selected, ...options]
            : options;

    function selectCategory(value: string) {
        if (
            pending.current ||
            value === (selected ? String(selected.id) : 'all')
        )
            return;
        pending.current = true;
        setFiltering(true);
        router.get(
            index.url({ query: value === 'all' ? {} : { category: value } }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => {
                    pending.current = false;
                    setFiltering(false);
                    requestAnimationFrame(() =>
                        trigger.current?.focus({ preventScroll: true }),
                    );
                },
            },
        );
    }

    return (
        <DropdownMenu
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                changeOpen(next);
            }}
        >
            <DropdownMenuTrigger asChild>
                <Button
                    ref={trigger}
                    type="button"
                    variant="outline"
                    disabled={filtering}
                    aria-label={`Filtrar leads por categoria: ${selected?.name ?? 'Todos'}`}
                    aria-busy={filtering}
                    className="min-h-11 max-w-full min-w-0 gap-2 rounded-xl border-white/10 bg-card px-4 text-muted-foreground disabled:opacity-60"
                >
                    <SlidersHorizontal
                        className="size-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    <span className="max-w-40 truncate">
                        {selected?.name ?? 'Todos'}
                    </span>
                    <ChevronDown
                        className="size-4 shrink-0"
                        aria-hidden="true"
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                collisionPadding={12}
                className="dashboard-theme max-h-[min(24rem,var(--radix-dropdown-menu-content-available-height))] w-72 max-w-[calc(100vw-1.5rem)] overflow-y-auto rounded-xl border-white/10 bg-popover p-1.5 text-foreground"
            >
                <DropdownMenuRadioGroup
                    value={selected ? String(selected.id) : 'all'}
                    onValueChange={selectCategory}
                    aria-label="Categoria dos leads"
                >
                    <DropdownMenuRadioItem
                        value="all"
                        className="min-h-11 rounded-lg"
                    >
                        Todos
                    </DropdownMenuRadioItem>
                    {visibleOptions.map((category) => (
                        <DropdownMenuRadioItem
                            key={category.id}
                            value={String(category.id)}
                            textValue={category.name}
                            className="min-h-11 gap-2.5 rounded-lg py-3"
                        >
                            <span
                                className="size-2.5 shrink-0 rounded-full border border-white/15"
                                style={{
                                    backgroundColor: categoryColor(
                                        category.color,
                                    ),
                                }}
                                aria-hidden="true"
                            />
                            <span className="min-w-0 [overflow-wrap:anywhere]">
                                {category.name}
                            </span>
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
                {processing && (
                    <p
                        role="status"
                        className="px-3 py-3 text-xs text-muted-foreground"
                    >
                        Carregando categorias...
                    </p>
                )}
                {error && (
                    <p
                        role="alert"
                        className="px-3 py-2 text-xs text-[#ffb4c0]"
                    >
                        {error}
                    </p>
                )}
                {(cursor || error) && (
                    <DropdownMenuItem
                        disabled={processing}
                        onSelect={(event) => {
                            event.preventDefault();
                            void loadMore();
                        }}
                        className="min-h-11 rounded-lg text-xs"
                    >
                        {error
                            ? 'Tentar novamente'
                            : 'Carregar mais categorias'}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
