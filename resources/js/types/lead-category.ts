export type LeadCategory = {
    id: number;
    name: string;
    color: string;
};

export type LeadCategoryMetric = LeadCategory & {
    leads_count: number;
    percentage: number;
};

export type LeadCategoryPage = {
    data: LeadCategoryMetric[];
    currentPage: number;
    hasMorePages: boolean;
};
