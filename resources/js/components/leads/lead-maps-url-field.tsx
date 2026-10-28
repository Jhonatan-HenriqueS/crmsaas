import { ClipboardPaste } from 'lucide-react';
import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { leadInputClass, mapsLinkFrom } from '@/lib/lead-format';

export function LeadMapsUrlField({
    id,
    value,
    onChange,
    onPasteLink,
    disabled,
    error,
    ...inputProps
}: Omit<ComponentProps<'input'>, 'value' | 'onChange'> & {
    id: string;
    value: string;
    onChange: (value: string) => void;
    onPasteLink: (link: string) => void;
    disabled: boolean;
    error?: string;
}) {
    async function paste() {
        try {
            onPasteLink(mapsLinkFrom(await navigator.clipboard.readText()));
        } catch {
            return;
        }
    }

    return (
        <>
            <div className="relative">
                <Input
                    {...inputProps}
                    id={id}
                    name="maps_url"
                    inputMode="url"
                    autoComplete="off"
                    maxLength={2048}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    disabled={disabled}
                    placeholder="https://maps.app.goo.gl/..."
                    aria-invalid={!!error}
                    aria-describedby={`${id}-error`}
                    className={`${leadInputClass} pr-14`}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={paste}
                    disabled={disabled}
                    aria-label="Colar link do Google Maps"
                    title="Colar link"
                    className="absolute top-1/2 right-1.5 size-8 -translate-y-1/2 rounded-lg border-white/10 bg-white/[0.06] text-muted-foreground hover:bg-primary/15 hover:text-foreground"
                >
                    <ClipboardPaste className="size-3.5" aria-hidden="true" />
                </Button>
            </div>
            <InputError
                id={`${id}-error`}
                message={error}
                className="text-[#ffb4c0]"
            />
        </>
    );
}
