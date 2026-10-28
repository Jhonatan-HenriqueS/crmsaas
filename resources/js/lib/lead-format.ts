/** Civil dates remain date-only strings; UTC is used only for day arithmetic. */
function civilDay(value: string): number {
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(0);
    date.setUTCFullYear(year, month - 1, day);
    date.setUTCHours(0, 0, 0, 0);
    return date.getTime() / 86_400_000;
}

export function localCivilDate(now = new Date()): string {
    return [
        String(now.getFullYear()).padStart(4, '0'),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-');
}

export function formatLeadDate(value: string): string {
    return value.split('-').reverse().join('/');
}

export function contactDeadline(value: string | null, today: string): string {
    if (value === null) return 'Não agendado';
    const days = civilDay(value) - civilDay(today);
    if (days === 0) return 'Hoje';
    if (days === 1) return 'Amanhã';
    if (days === -1) return 'Ontem';
    return days < 0
        ? `Atrasado há ${Math.abs(days)} dias`
        : `Faltam ${days} dias`;
}

/** Brazilian input to decimal strings, without floating-point conversion. */
export function parseLeadMoney(input: string): string | null | undefined {
    const value = input.trim();
    if (!value) return null;
    if (!/^(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{0,2})?$/.test(value)) {
        return undefined;
    }
    const [integer, fraction = ''] = value.replaceAll('.', '').split(',');
    const whole = integer.replace(/^0+(?=\d)/, '');
    if (whole.length > 10) return undefined;
    return `${whole}.${fraction.padEnd(2, '0')}`;
}

export function formatLeadMoney(value: string | null): string {
    if (value === null) return 'Não informado';
    const [integer, fraction = ''] = value.split('.');
    return `R$ ${new Intl.NumberFormat('pt-BR').format(BigInt(integer))},${fraction.padEnd(2, '0')}`;
}

/** Positive decimal-string amount check, without floating-point conversion. */
export function isPositiveLeadMoney(
    value: string | null | undefined,
): value is string {
    return typeof value === 'string' && /[1-9]/.test(value);
}

/** Refill the existing BRL input without changing null into zero. */
export function formatLeadMoneyInput(value: string | null): string {
    return value === null ? '' : formatLeadMoney(value).slice(3);
}

/** Defense in depth for database colors used in CSS; never interpolate arbitrary CSS. */
export function categoryColor(value: string): string {
    return /^#[0-9a-f]{6}$/i.test(value) ? value : '#6d54b5';
}

export const leadInputClass =
    'h-11 rounded-xl border-white/10 bg-secondary/60 text-base transition-[background-color,border-color,box-shadow] focus-visible:border-ring/40 focus-visible:bg-accent/70 focus-visible:shadow-[0_0_18px_#bca6ec1a] focus-visible:ring-0';
export const leadCreateClass =
    'min-h-11 rounded-xl bg-[var(--dashboard-green)] px-5 font-semibold text-[#0d2820] shadow-sm hover:bg-[#51e2b1]';
