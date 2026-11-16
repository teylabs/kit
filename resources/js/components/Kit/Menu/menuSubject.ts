/**
 * What a menu's actions act on: shown as a header row above its items (MenuSubject.vue). `title` names the record or row, `caption` adds context under it.
 * `kind` file, path or url cuts a long title in the middle (MiddleTruncate), keeping its start and its end ("Screenshot-2026-11…36.11-AM.png").
 */
export type MenuSubject = {
    title: string;
    caption?: string | null;
    kind?: 'file' | 'path' | 'url' | null;
    actions?: MenuSubjectAction[] | null;
};

/**
 * A quick verb on the subject itself (Open, Download, Copy link), shown as an icon in the header row. Each is a menu item, first in the arrow-key order, named by
 * `label` (also its tooltip). `url` makes it a link (`newTab` opens a new tab), else `onSelect` runs; `disabled` keeps it focusable with `reason` as its tooltip.
 * At most three show.
 */
export type MenuSubjectAction = {
    key?: string | null;
    label: string;
    icon: string;
    url?: string | null;
    newTab?: boolean | null;
    onSelect?: (() => void) | null;
    disabled?: boolean | null;
    reason?: string | null;
};
