import type { SeeAll } from '@/components/Kit/Action/seeAll';

/**
 * What a CollectionSection draws around its items (docs/collection-section.md), as the server sends it (`Tey\Kit\Features\Core\Data\CollectionSectionData`). A
 * dashboard widget's frame has the same fields, so it is one too. Only `title` is required: the rest default to a ready section with no header extras. Whether it draws a card is the component's `surface`, not the server's.
 */
export type CollectionSegment = {
    key: string;
    label: string;
    url: string;
    current?: boolean;
    count?: number | null;
};
export type CollectionSort = {
    label: string;
    options: { key: string; label: string }[];
    current: string;
    url: string;
    method: string;
    reload: string[];
};
export type CollectionAction = {
    label: string;
    url: string;
    method?: string;
    key?: string | null;
};
export type CollectionSectionData = {
    title: string;
    description?: string | null;
    state?: 'ready' | 'empty' | 'error';
    empty?: {
        variant?: string;
        title: string;
        description: string;
        action?: CollectionAction | null;
    } | null;
    error?: { message: string; retry?: { label: string } | null } | null;
    actions?: CollectionAction[];
    segments?: CollectionSegment[];
    sortBy?: CollectionSort | null;
    seeAll?: SeeAll | null;
    maxHeight?: string | null;
};
