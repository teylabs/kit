// The filter declaration DataTable's IndexToolbar and FilterPopover share: TableQuery's TableFilter (`table.choices`). Older declarations carry no `kind` and
// are single-value selects. A multi filter's value is its ticked option values comma-joined, as TableQuery reads it (`rooms=a,b`); empty means all.
export type FilterKind = 'select' | 'multi' | 'search';
export type FilterOption = {
    value: string;
    label: string;
    group?: string | null;
};
export type FilterDescriptor = {
    key: string;
    label: string;
    options: FilterOption[];
    kind?: FilterKind | null;
    default?: string | null;
    narrows?: string | null;
};
export type FilterValues = Record<string, string | null>;

export const filterKind = (filter: FilterDescriptor): FilterKind =>
    filter.kind ?? 'select';

/** A multi value as its ticked values, in the order given; empty and blank parts are dropped. */
export const splitMulti = (value: string | null | undefined): string[] =>
    (value ?? '')
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean);

/** Ticked values comma-joined in the options' order, so the same ticks always make the same URL; none is null (all). */
export function joinMulti(
    filter: FilterDescriptor,
    ticked: Iterable<string>,
): string | null {
    const set = new Set(ticked);
    const ordered = filter.options
        .map((option) => option.value)
        .filter((value) => set.has(value));
    return ordered.length ? ordered.join(',') : null;
}

/** A value with the filter's default in place of nothing, and nothing for an empty string. */
export const effective = (
    filter: FilterDescriptor,
    value: string | null | undefined,
): string | null =>
    value === null || value === undefined || value === ''
        ? (filter.default ?? null)
        : value;

/** The filters whose value differs from their default; search filters are never counted (they narrow the list, not the page). */
export function activeCount(
    filters: FilterDescriptor[],
    values: FilterValues,
): number {
    return filters.filter((filter) => {
        if (filterKind(filter) === 'search') return false;
        const now = effective(filter, values[filter.key]);
        const base = filter.default ?? null;
        return filterKind(filter) === 'multi'
            ? joinMulti(filter, splitMulti(now)) !==
                  joinMulti(filter, splitMulti(base))
            : now !== base;
    }).length;
}

/** The filters' values read from a query string, flat (`rooms=a,b`) or under `scope` (`filter[rooms]=a,b`, TableQuery's envelope). */
export function readValues(
    filters: FilterDescriptor[],
    search: string,
    scope?: string | null,
): FilterValues {
    const params = new URLSearchParams(search);
    return Object.fromEntries(
        filters
            .filter((filter) => filterKind(filter) !== 'search')
            .map((filter) => [
                filter.key,
                params.get(scope ? `${scope}[${filter.key}]` : filter.key) ||
                    null,
            ]),
    );
}

/** The query a visit sends: every param already in the URL kept (`page` aside: new filters start at the first page), the patch laid over it, and a value equal
 * to its default (or empty) dropped. */
export function patchQuery(
    filters: FilterDescriptor[],
    search: string,
    patch: FilterValues,
    scope?: string | null,
): Record<string, unknown> {
    const query: Record<string, unknown> = {};
    const nested: Record<string, string> = {};
    for (const [name, value] of new URLSearchParams(search)) {
        const inner = scope && name.match(new RegExp(`^${scope}\\[(.+)\\]$`));
        if (inner) nested[inner[1]] = value;
        else if (name !== 'page') query[name] = value;
    }
    const target: Record<string, unknown> = scope ? nested : query;
    for (const [key, value] of Object.entries(patch)) {
        const filter = filters.find((f) => f.key === key);
        const same =
            value === null ||
            value === '' ||
            (filter && value === (filter.default ?? null));
        if (same) delete target[key];
        else target[key] = value;
    }
    if (scope) {
        if (Object.keys(nested).length) query[scope] = nested;
        else delete query[scope];
    }
    return query;
}
