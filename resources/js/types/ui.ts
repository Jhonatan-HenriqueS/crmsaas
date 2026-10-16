import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type CrudOperation = 'created' | 'updated' | 'deleted';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
    operation?: CrudOperation;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
