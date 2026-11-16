/** One choice in an InlineDropdown: `href` makes it a link (an Inertia visit, a plain anchor with `external`); `section` groups runs of items under a label. */
export type InlineDropdownItem = {
    key: string;
    label: string;
    href?: string | null;
    external?: boolean;
    disabled?: boolean;
    section?: string | null;
};
