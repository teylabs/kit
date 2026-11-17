// The sample note and payloads for MarkdownApp (?markdown), kept out of the SFC so a payload's end tag cannot close its <script> block.
export const sample = `# Season kickoff

The **spring league** starts in April. Read the [schedule](https://example.test/schedule) or write to coaches@example.test, and see www.example.test.

## Before the first game

- [x] Register the team
- [ ] Book the field
- [ ] Order jerseys

| Week | Opponent | Field |
| :--- | :--- | ---: |
| 1 | Harbour FC | 3 |
| 2 | Northside | 1 |

> Games are ~~Saturdays~~ Sundays this year.

\`\`\`
kickoff: 10:00
\`\`\`

The fee is due by the first game.[^1]

[^1]: Refunds are pro-rated.`;

export const payloads = [
    '<script>window.pwned = 1</script>',
    '<img src=x onerror="window.pwned = 2">',
    '[click me](javascript:window.pwned=3)',
    '[click me too](jav&#x09;ascript:window.pwned=4)',
    '<iframe srcdoc="<script>parent.pwned=5</script>"></iframe>',
    '<svg onload="window.pwned=6"></svg>',
    '<math><mtext><table><mglyph><style><img src=x onerror=window.pwned=7></style></mglyph></table></mtext></math>',
    '<div>\n\n<img src=x onerror=window.pwned=8>\n\n</div>',
    '![x](data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9d2luZG93LnB3bmVkPTk+)',
    '<p style="position:fixed;inset:0">cover</p>',
    '<details open ontoggle="window.pwned=10">x</details>',
].join('\n\n');
