// Writes (or, with --check, compares) tests/fixtures/middle-truncate/parity.json: middleTruncate.ts's result for each case under one unit per grapheme,
// the measure MiddleTruncate.php counts with. tests/MiddleTruncateCheck.php holds the PHP twin to it; middle-truncate.test.mjs fails when it drifts.
import { mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const root = new URL('../../', import.meta.url);
const target = new URL('tests/fixtures/middle-truncate/parity.json', root);
const dir = mkdtempSync(join(tmpdir(), 'kit-middle-truncate-parity-'));
writeFileSync(join(dir, 'middleTruncate.ts'), readFileSync(new URL('resources/js/components/Kit/Data/middleTruncate.ts', root), 'utf8'));
const { graphemes, middleTruncate } = await import(pathToFileURL(join(dir, 'middleTruncate.ts')).href);

const values = [
    'A-8377 · 2009 Chevrolet Silverado C1500 LT',
    'paraframe-brand-guidelines-2026-final.pdf',
    'archive-of-the-whole-season-backup.tar.gz',
    '.env',
    '.env.production.local',
    'README',
    'a-file-name-with-no-extension-at-all',
    'version 2.5 notes for the release',
    'src/components/Kit/Data/MiddleTruncate.vue',
    'docs/guides/',
    'C:\\Users\\someone\\Documents\\Quarterly Report 2026.xlsx',
    'C:/Projects/long-project-name/build/output.log',
    '\\\\fileserver\\shared\\departments\\finance\\budget-final.xlsx',
    'https://cdn.paraframe.studio/clients/acme/2026/brand/guidelines/final-v3.pdf',
    'https://example.com/search/results?q=a+very+long+query&page=12#section-four',
    'https://example.com/?utm_source=newsletter&utm_campaign=autumn',
    'www.example.org/a/rather/long/path/to/some/page.html',
    'https://subdomain.example-with-a-long-host.com',
    'Café crème brûlée — naïve façade résumé',
    'Team 👩‍👩‍👧‍👦 family photos from the 🇨🇦 trip 🎉.jpg',
    '🇯🇵🇫🇷🇩🇪🇧🇷🇨🇦🇺🇸🇬🇧🇮🇹🇪🇸🇲🇽',
    'e\u0301e\u0301e\u0301e\u0301e\u0301e\u0301e\u0301e\u0301e\u0301e\u0301.txt',
    '東京都の非常に長いファイル名のドキュメント.pdf',
    'abc',
    'ab',
];
const widths = [0, 1, 2, 3, 4, 5, 6, 8, 10, 12, 15, 20, 25, 30, 40, 60];
const options = [{}, { minTail: 6 }, { kind: 'file' }, { kind: 'path' }, { kind: 'url' }];
const order = ({ head, middle, tail, end, truncated }) => ({ head, middle, tail, end, truncated });
const measure = text => graphemes(text).length;
const cases = [];
for (const value of values) {
    for (const width of widths) {
        for (const option of options) {
            cases.push({ value, width, kind: option.kind ?? 'auto', minTail: option.minTail ?? 0, expected: order(middleTruncate(value, width, measure, option)) });
        }
    }
}
const json = JSON.stringify(cases, null, 1) + '\n';
if (process.argv.includes('--check')) {
    if (readFileSync(target, 'utf8') !== json) {
        console.error('tests/fixtures/middle-truncate/parity.json is stale: run node tests/js/middle-truncate-parity.mjs');
        process.exit(1);
    }
    console.log(`parity.json matches middleTruncate.ts (${cases.length} cases)`);
} else {
    writeFileSync(target, json);
    console.log(`wrote ${cases.length} cases`);
}
