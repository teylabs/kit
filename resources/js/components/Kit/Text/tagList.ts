// TagList's logic (docs/flags.md): which tags show and which fold into "+N more". The server already orders them (flags first, the most severe first);
// a blank or repeated key is dropped. Flags are never folded before tags.

export type TagItem = {
    key: string;
    label: string;
    kind: 'flag' | 'tag';
    tone: string;
    tooltip?: string | null;
    href?: string | null;
};

export function shownTags(
    tags: readonly TagItem[] | null | undefined,
    max: number | null = 8,
): { shown: TagItem[]; hidden: TagItem[] } {
    const seen = new Set<string>();
    const all = (tags ?? []).filter((tag) => {
        if (!tag || !tag.label?.trim() || seen.has(tag.key)) {
            return false;
        }
        seen.add(tag.key);
        return true;
    });
    const ordered = [
        ...all.filter((tag) => tag.kind === 'flag'),
        ...all.filter((tag) => tag.kind !== 'flag'),
    ];
    if (max === null || ordered.length <= max) {
        return { shown: ordered, hidden: [] };
    }
    return { shown: ordered.slice(0, max), hidden: ordered.slice(max) };
}
