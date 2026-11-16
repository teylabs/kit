import type { ActionDescriptor } from '@/components/Kit/Action/actionPlacement';

/*
 * The data the comment and note components render (docs/comments.md). The server sends it whole: who wrote what, the colour, the files, and an action or a composer
 * for each thing the viewer may do. An absent action is one the server did not allow. The components make no request of their own beyond the Inertia <Form> or
 * visit each action names. An attachment has the notes feature's attachment fields, so a note's files pass through unchanged.
 */

/** A colour a comment or note carries: `tone` picks its accent (one of COMMENT_TONES; any other is muted), `label` names it, `meaning` shows beside the time. */
export type CommentColour = {
    key: string;
    label: string;
    tone: string;
    meaning?: string | null;
};

/** Who wrote it: their name, their initials for the avatar, and their photo when they have one. */
export type CommentAuthor = {
    name: string;
    initials: string;
    avatar_url?: string | null;
    avatar_url_2x?: string | null;
    /** The person's colour (AvatarColor on the server), so their initials match everywhere else they appear. */
    avatar_color?: string | null;
};

/** One attached file. An image whose thumbnail is still being made (`thumb_pending`) shows a placeholder, never the original. */
export type CommentAttachment = {
    id: string;
    name: string;
    mime: string;
    url: string;
    size?: number | null;
    thumb_url?: string | null;
    thumb_pending?: boolean;
};

/**
 * A composer, for a new comment, a reply or an edit: `submit` is the action it saves to (an edit's is its PUT or PATCH), `colours` the palette (a choice shows from two
 * up), `media` the files it takes (null: none), `fields` the extra values every save posts (a record's `subject_type` and `subject_id`, a parent's id), and `author`
 * the person writing, for the avatar.
 */
export type CommentComposerData = {
    submit: ActionDescriptor;
    placeholder: string;
    label?: string | null;
    colours?: CommentColour[];
    default_colour?: string | null;
    media?: {
        accept: string[];
        max_files: number;
        max_kb: number;
        hint?: string | null;
    } | null;
    fields?: Record<string, string | number>;
    author?: CommentAuthor | null;
};

/**
 * One comment or note: its body, author, time (`created_at`, RFC 3339), colour, files and version (sent with every change, so a stale one is refused), the record it
 * is about (`subject`), what the viewer may do (`editor`, `reply`, `delete`) and its replies. `noun` names it in labels ("note", default "comment").
 */
export type CommentData = {
    id: number | string;
    body: string;
    author: CommentAuthor | null;
    created_at: string;
    version: number;
    colour?: CommentColour | null;
    attachments?: CommentAttachment[];
    subject?: { label: string; url?: string | null } | null;
    noun?: string;
    editor?: CommentComposerData | null;
    reply?: CommentComposerData | null;
    delete?: ActionDescriptor | null;
    replies?: CommentData[];
};

/** A run of comments shown together (a discussion under a thread's heading), keyed by `id`. */
export type CommentGroup = { id: number | string; comments: CommentData[] };

export type CommentTone =
    | 'muted'
    | 'info'
    | 'success'
    | 'warning'
    | 'danger'
    | 'yellow'
    | 'pink'
    | 'blue'
    | 'green';

// Whole class strings, never built from the tone (Tailwind finds classes by scanning the source). The status tones are the kit's text tokens, the four note colours
// the notes feature's palette.
const left: Record<CommentTone, string> = {
    muted: 'border-l-muted-foreground',
    info: 'border-l-info-text',
    success: 'border-l-success-text',
    warning: 'border-l-warning-text',
    danger: 'border-l-destructive-text',
    yellow: 'border-l-amber-400',
    pink: 'border-l-pink-400',
    blue: 'border-l-sky-400',
    green: 'border-l-emerald-400',
};
const top: Record<CommentTone, string> = {
    muted: 'border-t-muted-foreground',
    info: 'border-t-info-text',
    success: 'border-t-success-text',
    warning: 'border-t-warning-text',
    danger: 'border-t-destructive-text',
    yellow: 'border-t-amber-400',
    pink: 'border-t-pink-400',
    blue: 'border-t-sky-400',
    green: 'border-t-emerald-400',
};
const dots: Record<CommentTone, string> = {
    muted: 'bg-muted-foreground',
    info: 'bg-info-text',
    success: 'bg-success-text',
    warning: 'bg-warning-text',
    danger: 'bg-destructive-text',
    yellow: 'bg-amber-400',
    pink: 'bg-pink-400',
    blue: 'bg-sky-400',
    green: 'bg-emerald-400',
};

export const COMMENT_TONES = Object.keys(left) as CommentTone[];

const known = (tone: string | null | undefined): CommentTone =>
    tone && tone in left ? (tone as CommentTone) : 'muted';

/** The accent edge's colour class for a tone, on the left or top edge; an unknown tone is muted. */
export const accentClass = (
    tone: string | null | undefined,
    edge: 'left' | 'top' = 'left',
): string => (edge === 'top' ? top : left)[known(tone)];

/** The colour dot's class for a tone (a colour choice's swatch); an unknown tone is muted. */
export const toneDot = (tone: string | null | undefined): string =>
    dots[known(tone)];

/** One key per attempt: a retry of the same save (a lost response) replays it instead of writing twice. */
export const intentKey = (): string => crypto.randomUUID();
