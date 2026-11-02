// Guards the browser suite's size (node count-check.mjs [tap file], after node --test wrote a TAP report): a merge resolution once cut run.mjs to one test and CI
// still passed. Fails when run.mjs declares fewer tests than the committed floor in expected-count, or the TAP report ran fewer, or any failed. Raise the floor
// with each new browser test; lowering it is a reviewed change.
import { readFileSync } from 'node:fs';

const here = (file) => new URL(file, import.meta.url);
const floor = Number(readFileSync(here('expected-count'), 'utf8').trim());
const declared = (readFileSync(here('run.mjs'), 'utf8').match(/^test\(/gm) ?? []).length;
const problems = [];
if (!Number.isInteger(floor) || floor < 1) problems.push(`expected-count must hold a positive integer, not "${floor}"`);
if (declared < floor) problems.push(`run.mjs declares ${declared} tests, below the floor of ${floor} in expected-count`);
const tapFile = process.argv[2];
if (tapFile) {
    const tap = readFileSync(tapFile, 'utf8');
    const count = (name) => Number(tap.match(new RegExp(`^# ${name} (\\d+)$`, 'm'))?.[1] ?? NaN);
    const [tests, pass, fail] = [count('tests'), count('pass'), count('fail')];
    if (!(tests >= floor)) problems.push(`the TAP report ran ${tests} tests, below the floor of ${floor}`);
    if (!(pass >= floor)) problems.push(`the TAP report passed ${pass} tests, below the floor of ${floor}`);
    if (fail !== 0) problems.push(`the TAP report has ${fail} failures`);
}
if (problems.length) {
    console.error(problems.map(problem => `browser count-check: ${problem}`).join('\n'));
    process.exit(1);
}
console.log(`browser count-check: ${declared} declared${tapFile ? ', all ran and passed' : ''}, floor ${floor}`);
