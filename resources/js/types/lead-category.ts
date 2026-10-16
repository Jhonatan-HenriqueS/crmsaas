export type LeadCategory = {
    id: number;
    name: string;
    color: string;
};

export type LeadCategoryPage = {
    data: LeadCategory[];
    currentPage: number;
    hasMorePages: boolean;
};
