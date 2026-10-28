import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { leadInputClass } from '@/lib/lead-format';

export function LeadMoneyField({
    id,
    name,
    label,
    hint,
    value,
    onChange,
    disabled,
    error,
    describedBy,
}: {
    id: string;
    name: string;
    label: string;
    hint: string;
    value: string;
    onChange: (value: string) => void;
    disabled: boolean;
    error?: string;
    describedBy?: string;
}) {
    return (
        <div className="min-w-0 space-y-2.5">
            <Label htmlFor={id}>
                {label}{' '}
                <span className="text-xs font-normal text-muted-foreground">
                    {hint}
                </span>
            </Label>
            <div className="relative">
                <span
                    aria-hidden="true"
                    className="pointer-events-none absolute top-3 left-3 text-sm text-muted-foreground"
                >
                    R$
                </span>
                <Input
                    id={id}
                    name={name}
                    inputMode="decimal"
                    autoComplete="off"
                    maxLength={24}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    disabled={disabled}
                    placeholder="0,00"
                    aria-invalid={!!error}
                    aria-describedby={`${id}-error ${describedBy ?? ''}`.trim()}
                    className={`${leadInputClass} pl-10 tabular-nums`}
                />
            </div>
            <InputError
                id={`${id}-error`}
                message={error}
                className="text-[#ffb4c0]"
            />
        </div>
    );
}
