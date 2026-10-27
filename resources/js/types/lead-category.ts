export type LeadCategory = {
    id: number;
    name: string;
    color: string;
    is_sale_completed: boolean;
};

export type LeadCategoryMetric = LeadCategory & {
    leads_count: number;
    deletion_confirmation: string | null;
    percentage: number;
};

export type LeadCategoryPage = {
    data: LeadCategoryMetric[];
    currentPage: number;
    hasMorePages: boolean;
};
