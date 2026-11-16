/**
 * What a menu's actions act on: shown as a header row above its items (MenuSubject.vue). `title` names the record or row, `caption` adds context under it.
 * `kind` file, path or url cuts a long title in the middle (MiddleTruncate), keeping its start and its end ("Screenshot-2026-11…36.11-AM.png").
 */
export type MenuSubject = {
    title: string;
    caption?: string | null;
    kind?: 'file' | 'path' | 'url' | null;
};
