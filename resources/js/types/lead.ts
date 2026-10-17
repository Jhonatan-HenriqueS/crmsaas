import type { LeadCategory } from '@/types/lead-category';

export type Lead = {
    id: number;
    category_id: number;
    category: LeadCategory;
    name: string;
    description: string | null;
    first_contact_date: string;
    next_contact_date: string | null;
    potential_amount: string | null;
    charged_amount: string | null;
};

export type LeadPage = {
    data: Lead[];
    currentPage: number;
    hasMorePages: boolean;
    total: number;
};

export type LeadCategoryOptions = {
    data: LeadCategory[];
    nextCursor: string | null;
};
