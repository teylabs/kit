import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import './app.css';
import { COMMENT_TONES, CommentForm, CommentItem, CommentThread, NoteCard, type CommentComposerData, type CommentData } from '@kit/ui';

// The comment and note components (comments.html, built with the auth pages by vite.auth.config.js) on the real @inertiajs/vue3, so CommentForm's <Form>, transform and
// CommentItem's delete visit run as in an app. The test answers each write under /@acme/comments with Playwright's page.route. A thread with a composer, a comment the
// viewer may edit, reply to and delete (with two files, one image's thumbnail pending, a subject and a reply), and a read-only one; then a gallery of NoteCards: every
// tone on each edge and width, compact and flush, and a CommentItem in the compact variant. `?dark` paints the dark tokens.
if (new URLSearchParams(window.location.search).has('dark')) document.documentElement.classList.add('dark');
const author = { name: 'Sam Reed', initials: 'SR' };
const colours = [
    { key: 'yellow', label: 'Yellow', tone: 'yellow' },
    { key: 'blue', label: 'Blue', tone: 'blue' },
    { key: 'urgent', label: 'Urgent', tone: 'danger', meaning: 'Needs an answer today' },
];
const composer: CommentComposerData = {
    submit: { label: 'Post', url: '/@acme/comments', method: 'post' },
    placeholder: 'Write a comment…',
    colours,
    default_colour: 'yellow',
    media: { accept: ['image/png', 'image/jpeg', 'application/pdf'], max_files: 3, max_kb: 1024, hint: 'PNG, JPEG or PDF, up to 1 MB.' },
    fields: { subject_type: 'project', subject_id: 7 },
    author,
};
const reply: CommentData = {
    id: 2,
    body: 'Done: the second draft is attached.',
    author: { name: 'Ada Lane', initials: 'AL' },
    created_at: '2026-11-14T09:30:00Z',
    version: 1,
    colour: colours[1],
    // A delete that needs typing: a menu can't ask for it, so it shows disabled.
    delete: { label: 'Delete', url: '/@acme/comments/2', method: 'delete', confirm: { description: 'Type DELETE to confirm.', label: 'Delete', typed: 'DELETE' } },
};
const editable: CommentData = {
    id: 1,
    body: 'Please check the measurements on the second floor before Friday.',
    author,
    created_at: '2026-11-13T15:00:00Z',
    version: 3,
    colour: colours[2],
    attachments: [
        { id: 'f-plan', name: 'floor-plan.pdf', mime: 'application/pdf', url: '/@acme/files/f-plan', size: 20480 },
        { id: 'f-photo', name: 'hallway.png', mime: 'image/png', url: '/@acme/files/f-photo', thumb_url: '/@acme/files/f-photo/thumb', thumb_pending: true },
    ],
    subject: { label: 'Project 7', url: '/@acme/projects/7' },
    editor: { ...composer, submit: { label: 'Save', url: '/@acme/comments/1', method: 'put' }, fields: {} },
    reply: { ...composer, submit: { label: 'Reply', url: '/@acme/comments', method: 'post' }, placeholder: 'Write a reply…', fields: { parent_id: 1 } },
    delete: { label: 'Delete', url: '/@acme/comments/1', method: 'delete', confirm: { title: 'Delete this comment?', description: 'The comment and its files are removed for everyone.', label: 'Delete comment' } },
    replies: [reply],
};
const readOnly: CommentData = { id: 3, body: 'A comment the viewer may only read.', author: { name: 'Ada Lane', initials: 'AL' }, created_at: '2026-11-12T08:00:00Z', version: 1, colour: null, subject: { label: 'Project 8' } };
const props = { errors: {}, comments: [editable, readOnly], composer };
// `?adopt`: the opt-ins an adopting app needs (absolute time, an image gallery, a meta link, menu icons, composer slots), on a comment with two images and a PDF.
const adopt = new URLSearchParams(window.location.search).has('adopt');
const gallery: CommentData = {
    ...editable,
    id: 9,
    replies: [],
    attachments: [
        { id: 'g-1', name: 'kitchen.jpg', mime: 'image/jpeg', url: '/@acme/files/g-1', thumb_url: '/@acme/files/g-1/thumb' },
        { id: 'g-2', name: 'roof.png', mime: 'image/png', url: '/@acme/files/g-2', thumb_pending: true },
        { id: 'g-3', name: 'report.pdf', mime: 'application/pdf', url: '/@acme/files/g-3', size: 4096 },
    ],
};

const Page = {
    props: ['comments', 'composer'],
    render(this: { comments: CommentData[]; composer: CommentComposerData }) {
        const cards = COMMENT_TONES.flatMap((tone) =>
            (['left', 'top'] as const).flatMap((edge) =>
                (['thin', 'medium', 'wide'] as const).map((width) =>
                    h(NoteCard, { accent: { tone, label: tone }, accentEdge: edge, accentWidth: width, author, 'data-look': `${tone}-${edge}-${width}` }, () => 'Note text.'),
                ),
            ),
        );
        const adopted = adopt
            ? [
                  h('section', { 'aria-label': 'Adopted', 'data-adopted': '' }, [
                      h(CommentItem, { comment: gallery, timeFormat: 'absolute', attachmentLayout: 'gallery', menuIcons: true, onViewImage: (image: { name: string }, images: unknown[]) => { (window as unknown as { viewed: string }).viewed = `${image.name}/${images.length}`; } }, {
                          meta: ({ comment }: { comment: CommentData }) => h('a', { href: '/@acme/cases/7', class: 'text-foreground underline', 'data-case-link': '' }, `Case 7 · ${comment.noun ?? 'comment'}`),
                      }),
                      h(CommentForm, { composer }, {
                          before: () => h('label', { class: 'text-sm', 'data-composer-before': '' }, ['Case ', h('input', { name: 'case_id', value: '7', class: 'border-border rounded border px-1' })]),
                          after: () => h('label', { class: 'text-sm', 'data-composer-after': '' }, [h('input', { type: 'checkbox', name: 'notify', value: '1' }), ' Notify the team']),
                      }),
                      // Below the composer, so it moves up when the open composer collapses: a press on it must still click it.
                      h('button', { type: 'button', 'data-below-composer': '', onClick: () => { const w = window as unknown as { belowClicks: number }; w.belowClicks = (w.belowClicks ?? 0) + 1; } }, 'Show more'),
                  ]),
              ]
            : [];
        return [
            ...adopted,
            h(CommentThread, { title: 'Discussion', comments: this.comments, composer: this.composer }),
            // Groups replace comments even when none has a comment: this one shows its empty line, not the comments it was also given.
            // An editor the host keeps mounted after a save (CommentItem closes its own).
            h('section', { 'aria-label': 'Kept editor', 'data-kept-editor': '' }, [h(CommentForm, { composer: editable.editor, comment: editable, placeholder: 'Edit the comment…' })]),
            h(CommentThread, { title: 'No discussions', comments: this.comments, groups: [{ id: 'none', comments: [] }], level: 3, 'data-empty-groups': '' }),
            h('section', { 'aria-label': 'Looks', class: 'grid gap-3', 'data-looks': '' }, [
                h(NoteCard, { accent: { tone: 'unknown', label: 'Other' }, density: 'compact', authorStyle: 'none', 'data-look': 'fallback' }, () => 'A compact row with an unknown tone.'),
                h(NoteCard, { accent: { tone: 'green', label: 'Green' }, density: 'flush', author, 'data-look': 'flush' }, () => 'A flush tile.'),
                h(CommentItem, { comment: editable, variant: 'compact', 'data-look': 'compact-item' }),
                ...cards,
            ]),
        ];
    },
};
(window as unknown as { kitInitialPage: unknown }).kitInitialPage = { component: 'Comments', props, url: '/@acme/comments', version: null, flash: {}, clearHistory: false, encryptHistory: false };
createInertiaApp({
    page: (window as unknown as { kitInitialPage: never }).kitInitialPage,
    resolve: () => Page,
    setup: ({ el, App, props, plugin }) => createApp({ render: () => h(App, props) }).use(plugin).mount(el),
    progress: false,
});
