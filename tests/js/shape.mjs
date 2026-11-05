// Formatting-proof views of TypeScript source, for checks that pin an API (the hand-written contract, an exported signature) rather than markup: the same
// answer however Prettier wraps the code. `flat` collapses whitespace, drops a union's leading `|` and the separators before a closing bracket; `members`
// reads `export interface Name { ... }` into a map of member name to its normalised `name?: type`.

/** The text with whitespace collapsed and no space or separator before a closer, nor space after an opener. */
export function flat(text) {
    return text
        .replace(/\s+/g, ' ')
        .replace(/([({[<]) /g, '$1')
        .replace(/[;,]? ([)}\]>])/g, '$1')
        .replace(/([:=(<,|]) \| /g, '$1 ')
        .replace(/\(\| /g, '(')
        // A wrapped attribute value is padded inside its quotes: `v-if=" a || b "`.
        .replace(/="\s*([^"]*?)\s*"/g, '="$1"')
        // Prettier's own rewrites: a lone arrow parameter gains parentheses, and a member chain breaks before its dots.
        .replace(/\((\w+)\) =>/g, '$1 =>')
        .replace(/([\w)\]]) \.(?=[a-zA-Z_$])/g, '$1.')
        .trim();
}

/** The body of `export interface <name> { … }`, matched by braces, or null. */
function body(source, name) {
    const start = source.search(new RegExp(`export interface ${name}\\b[^{]*\\{`));
    if (start === -1) return null;
    let depth = 0;
    for (let i = source.indexOf('{', start); i < source.length; i++) {
        if (source[i] === '{') depth++;
        if (source[i] === '}' && --depth === 0) return source.slice(source.indexOf('{', start) + 1, i);
    }
    return null;
}

/** `export interface <name>`'s members: member name to `name?: type`, normalised with `flat`. Comments are dropped. */
export function members(source, name) {
    const raw = body(source.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/.*$/gm, ''), name);
    if (raw === null) return null;
    // A wrapped union or intersection continues its member: join `\n | x` back onto the line before.
    const text = raw.replace(/\n\s*([|&])/g, ' $1');
    const parts = [];
    let depth = 0, current = '';
    for (const [index, character] of [...text].entries()) {
        if ('({[<'.includes(character)) depth++;
        // The `>` of an arrow (`=>`) closes nothing.
        if (')}]>'.includes(character) && !(character === '>' && text[index - 1] === '=')) depth--;
        if (depth === 0 && (character === ';' || character === '\n') && current.trim() && /^\s*[\w$'"]+\??\s*:/.test(current) && !/[|&,:(<{]\s*$/.test(current)) {
            parts.push(current);
            current = '';
            continue;
        }
        current += character;
    }
    if (current.trim()) parts.push(current);
    return new Map(parts.map(part => flat(part).replace(/[;,]$/, '')).filter(Boolean).map(part => [part.match(/^['"]?([\w$]+)/)[1], part]));
}
