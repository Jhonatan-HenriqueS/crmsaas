import type { LeadCategory } from '../types/lead-category';

export type LeadTransition = 'complete' | 'reopen';

/** The category identity comes from the authorized server payload, never its name or color. */
export function leadTransition(
    previous: LeadCategory | null,
    selected: LeadCategory | null,
): LeadTransition | null {
    if (!selected || previous?.is_sale_completed === selected.is_sale_completed)
        return null;
    if (selected.is_sale_completed) return 'complete';
    return previous?.is_sale_completed ? 'reopen' : null;
}
