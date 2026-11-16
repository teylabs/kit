/**
 * A menu's actions in sections: each run of consecutive actions with the same `section` is one section, labelled by that group (null: no label). With no
 * `section` on any action the menu is one unlabelled section and renders as before (`grouped` false). Each item keeps its index in the whole list, for its ids.
 */
export type MenuSection<A> = {
    key: string;
    label: string | null;
    items: { action: A; index: number }[];
};

export function menuSections<
    A extends { section?: string | null; destructive?: boolean | null },
>(actions: A[]): { grouped: boolean; sections: MenuSection<A>[] } {
    const grouped = actions.some((action) => Boolean(action.section));
    // No sections: a trailing run of destructive rows after other rows still gets its divider (an unlabelled last section), so Delete never sits flush.
    if (!grouped) {
        let split = actions.length;
        while (split > 0 && actions[split - 1].destructive) split--;
        if (split > 0 && split < actions.length) {
            const items = actions.map((action, index) => ({ action, index }));
            return {
                grouped: true,
                sections: [
                    { key: 'all', label: null, items: items.slice(0, split) },
                    {
                        key: 'destructive',
                        label: null,
                        items: items.slice(split),
                    },
                ],
            };
        }
    }
    const sections: MenuSection<A>[] = [];
    actions.forEach((action, index) => {
        const label = action.section || null;
        const last = sections[sections.length - 1];
        if (last && last.label === label) last.items.push({ action, index });
        else
            sections.push({
                key: `${sections.length}-${label ?? ''}`,
                label,
                items: [{ action, index }],
            });
    });

    return {
        grouped,
        sections: grouped
            ? sections
            : [
                  {
                      key: 'all',
                      label: null,
                      items: actions.map((action, index) => ({
                          action,
                          index,
                      })),
                  },
              ],
    };
}
