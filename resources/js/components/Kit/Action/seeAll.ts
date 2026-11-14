/**
 * A list's one "see all" link, as the server sends it (`Tey\Kit\Features\Core\Data\SeeAll`, docs/see-all.md): a noun-specific `label` ("All cases"), its
 * `url`, an optional `count` drawn after the label ("All cases (42) →"), and `placement`: `footer` (the default, for lists) or `header` (summaries, stats
 * and charts).
 */
export type SeeAll = {
    label: string;
    url: string;
    count?: number | null;
    placement?: 'footer' | 'header';
};

/** Where a card draws the link: its own `placement`, `footer` when absent. Null when there is none. */
export function seeAllPlacement(
    seeAll: SeeAll | null | undefined,
): 'footer' | 'header' | null {
    return seeAll ? (seeAll.placement ?? 'footer') : null;
}
