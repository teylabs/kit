/*
 * Splits a plain-text email body at its forwarded-message blocks (docs/text.md, EmailMessage `forwarded`). Plain TypeScript, no DOM.
 * A block starts at a marker and its run of header lines:
 *   Gmail:      "---------- Forwarded message ---------", then From:, Date:, Subject:, To:
 *   Apple Mail: "Begin forwarded message:", a blank line, then From:, Subject:, Date:, To:
 *   Outlook:    an optional "-----Original Message-----" or "_____" rule, then a From: … Sent: … To: … Subject: run
 * The text before the first block is `body`; each block keeps its headers and the text up to the next block. A forward of a forward is
 * simply the next block, so nesting reads in order (outermost first) with `depth` counting from 1.
 */
export type ForwardHeader = { name: string; value: string };
export type Forward = {
    from: string | null;
    sender: string | null;
    date: string | null;
    subject: string | null;
    headers: ForwardHeader[];
    body: string;
    depth: number;
};
export type ForwardSplit = { body: string; forwards: Forward[] };

const MARKER =
    /^\s*(?:-{2,}\s*Forwarded message\s*-{2,}|Begin forwarded message:)\s*$/i;
const RULE = /^\s*(?:-{2,}\s*Original Message\s*-{2,}|_{10,})\s*$/i;
const HEADER = /^\s*(From|To|Cc|Bcc|Date|Sent|Subject|Reply-To)\s*:\s*(.*)$/i;

// The header run that starts at `start` (blank lines before it are skipped), or null when there is none.
function headerRun(
    lines: string[],
    start: number,
): { headers: ForwardHeader[]; end: number } | null {
    let i = start;
    while (i < lines.length && lines[i].trim() === '') i++;
    const headers: ForwardHeader[] = [];
    while (i < lines.length) {
        const match = HEADER.exec(lines[i]);
        if (!match) break;
        headers.push({
            name: match[1].replace(/^\w/, (c) => c.toUpperCase()),
            value: match[2].trim(),
        });
        i++;
    }
    return headers.length ? { headers, end: i } : null;
}

const value = (headers: ForwardHeader[], ...names: string[]) =>
    headers.find((h) => names.includes(h.name.toLowerCase()))?.value || null;

// "Ana Lopez <ana@example.test>" reads "Ana Lopez"; a bare address reads as itself; quotes and mailto brackets are dropped.
export function senderName(from: string | null): string | null {
    if (!from) return null;
    const angled = /^(.*?)\s*[<[](?:mailto:)?([^>\]]+)[>\]]\s*$/i.exec(from);
    const name = (angled ? angled[1] : from).replace(/^["'\s]+|["'\s]+$/g, '');
    return name || angled?.[2].trim() || from.trim() || null;
}

// Where a forwarded block starts at line i: the line after its headers, or null. Outlook has no marker, so a header run counts
// only when it has From and Sent (a quoted "From:" line in prose does not).
function blockAt(
    lines: string[],
    i: number,
): { headers: ForwardHeader[]; end: number } | null {
    if (MARKER.test(lines[i]))
        return headerRun(lines, i + 1) ?? { headers: [], end: i + 1 };
    if (RULE.test(lines[i])) {
        const run = headerRun(lines, i + 1);
        return run && value(run.headers, 'from') ? run : null;
    }
    if (/^\s*From\s*:/i.test(lines[i])) {
        const run = headerRun(lines, i);
        return run && value(run.headers, 'sent') ? run : null;
    }
    return null;
}

const trimBlank = (lines: string[]) =>
    lines
        .join('\n')
        .replace(/^\s*\n/, '')
        .replace(/\s+$/, '');

export function splitForwards(text: string | null | undefined): ForwardSplit {
    const source = text ?? '';
    const lines = source.split(/\r?\n/);
    const forwards: Forward[] = [];
    let lead: string[] | null = null;
    let current: string[] = [];
    let open: Omit<Forward, 'body'> | null = null;
    const close = () => {
        if (open) forwards.push({ ...open, body: trimBlank(current) });
        else lead = current;
    };
    for (let i = 0; i < lines.length; i++) {
        const block = blockAt(lines, i);
        if (!block) {
            current.push(lines[i]);
            continue;
        }
        close();
        const from = value(block.headers, 'from');
        open = {
            from,
            sender: senderName(from),
            date: value(block.headers, 'date', 'sent'),
            subject: value(block.headers, 'subject'),
            headers: block.headers,
            depth: forwards.length + 1,
        };
        current = [];
        i = block.end - 1;
    }
    if (!open) return { body: source, forwards: [] };
    close();
    return {
        body: (lead as string[] | null)?.join('\n').replace(/\s+$/, '') ?? '',
        forwards,
    };
}

// "Forwarded from Ana Lopez, Mon, Nov 9, 2026 at 3:14 PM"; the parts that are missing are left out.
export function forwardSummary(
    forward: Pick<Forward, 'sender' | 'date'>,
): string {
    const parts = [forward.sender, forward.date].filter(Boolean);
    return parts.length
        ? `Forwarded from ${parts.join(', ')}`
        : 'Forwarded message';
}
