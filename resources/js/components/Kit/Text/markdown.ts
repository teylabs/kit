import DOMPurify, { type DOMPurify as Purifier } from 'dompurify';
import { micromark } from 'micromark';
import { gfm, gfmHtml } from 'micromark-extension-gfm';

/*
 * User-authored Markdown to safe HTML (docs/markdown.md). Two layers, so neither has to be perfect alone:
 *   1. micromark with GFM (tables, strikethrough, autolink literals, task lists, footnotes) and its safe defaults: raw HTML in the source is escaped
 *      as text, never passed through, and a link or image URL outside http(s), mailto (and, for links, irc and xmpp) is dropped.
 *   2. DOMPurify over the result with an allowlist of the tags and attributes micromark emits, then one DOM pass: headings demoted so the first level
 *      is `headingLevel`, every link given rel="noopener noreferrer nofollow" (and target="_blank" only when `newTab`), URLs checked again (no
 *      javascript:, vbscript: or data:, images too), a task-list checkbox kept only disabled and named by its item, and footnote ids made unique per render.
 * It needs a DOM (DOMPurify's): without one (server rendering) it fails closed and returns the source as escaped text in one paragraph.
 */
export type MarkdownOptions = {
    /** The level a `#` heading renders at; deeper headings follow, capped at h6. Default 3, so user content never emits an h1 or h2. */
    headingLevel?: 1 | 2 | 3 | 4 | 5 | 6;
    /** Open links to other pages in a new tab (target="_blank"). In-page footnote links never do. */
    newTab?: boolean;
};

const TAGS = [
    'a',
    'blockquote',
    'br',
    'code',
    'del',
    'em',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'hr',
    'img',
    'input',
    'li',
    'ol',
    'p',
    'pre',
    'section',
    'strong',
    'sup',
    'table',
    'tbody',
    'td',
    'th',
    'thead',
    'tr',
    'ul',
];
const ATTRIBUTES = [
    'href',
    'src',
    'alt',
    'title',
    'align',
    'start',
    'type',
    'checked',
    'disabled',
    'id',
    'class',
    'aria-describedby',
    'aria-label',
];
// http(s), mailto, an in-page #anchor or a relative path; any other scheme (javascript:, vbscript:, data:, file:) is refused.
const SAFE_URL =
    /^(?:(?:https?|mailto):|[^a-z]|[a-z0-9+.-]*(?:[^a-z0-9+.\-:]|$))/i;
const CLASSES = new Set(['footnotes', 'sr-only', 'data-footnote-backref']);

let purifier: Purifier | null = null;
let renders = 0;

function purify(): Purifier | null {
    if (purifier === null && typeof window !== 'undefined')
        purifier = DOMPurify(window);
    return purifier?.isSupported ? purifier : null;
}

function escape(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// A URL the browser would read as safe: control characters and whitespace removed first, as a browser ignores them in a scheme (`jav&#x09;ascript:`).
function safeUrl(value: string): boolean {
    return SAFE_URL.test(value.replace(/[\u0000- \u007f-\u009f]/g, ''));
}

export function renderMarkdown(
    source: string,
    options: MarkdownOptions = {},
): string {
    const headingLevel = options.headingLevel ?? 3;
    const prefix = `kit-md-${++renders}-`;
    const dom = purify();
    if (dom === null)
        return source.trim() === '' ? '' : `<p>${escape(source)}</p>`;

    const html = micromark(source, {
        extensions: [gfm()],
        htmlExtensions: [gfmHtml({ clobberPrefix: prefix })],
        allowDangerousHtml: false,
        allowDangerousProtocol: false,
    });
    const body = dom.sanitize(html, {
        ALLOWED_TAGS: TAGS,
        ALLOWED_ATTR: ATTRIBUTES,
        ALLOWED_URI_REGEXP: SAFE_URL,
        ALLOW_DATA_ATTR: false,
        ALLOW_ARIA_ATTR: false,
        ALLOW_UNKNOWN_PROTOCOLS: false,
        RETURN_DOM: true,
    }) as HTMLElement;
    const document = body.ownerDocument;

    for (const element of Array.from(body.querySelectorAll('*'))) {
        for (const name of ['href', 'src']) {
            const value = element.getAttribute(name);
            if (value !== null && !safeUrl(value))
                element.removeAttribute(name);
        }
        const id = element.getAttribute('id');
        if (id !== null && !id.startsWith(prefix))
            element.setAttribute('id', `${prefix}${id}`);
        const describedBy = element.getAttribute('aria-describedby');
        if (describedBy !== null)
            element.setAttribute('aria-describedby', `${prefix}${describedBy}`);
        const classes = Array.from(element.classList).filter((name) =>
            CLASSES.has(name),
        );
        if (classes.length > 0)
            element.setAttribute('class', classes.join(' '));
        else element.removeAttribute('class');
    }

    for (const heading of Array.from(
        body.querySelectorAll('h1, h2, h3, h4, h5, h6'),
    )) {
        const level = Math.min(
            6,
            headingLevel + Number(heading.tagName[1]) - 1,
        );
        const demoted = document.createElement(`h${level}`);
        for (const attribute of Array.from(heading.attributes))
            demoted.setAttribute(attribute.name, attribute.value);
        demoted.append(...Array.from(heading.childNodes));
        heading.replaceWith(demoted);
    }

    for (const link of Array.from(body.querySelectorAll('a'))) {
        const href = link.getAttribute('href');
        if (href === null || href.startsWith('#')) continue;
        link.setAttribute('rel', 'noopener noreferrer nofollow');
        if (options.newTab) link.setAttribute('target', '_blank');
    }

    for (const input of Array.from(body.querySelectorAll('input'))) {
        const item = input.parentElement;
        if (
            input.getAttribute('type') !== 'checkbox' ||
            item?.tagName !== 'LI'
        ) {
            input.remove();
            continue;
        }
        input.setAttribute('disabled', '');
        const label = (item.textContent ?? '').trim().slice(0, 200);
        input.setAttribute('aria-label', label === '' ? 'Task' : label);
    }

    return body.innerHTML;
}
