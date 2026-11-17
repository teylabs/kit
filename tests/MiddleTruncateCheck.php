<?php

// MiddleTruncate (kit PHP support): the server's character-count twin of middleTruncate.ts. Every case in tests/fixtures/middle-truncate/parity.json is
// the TypeScript's own result under one unit per grapheme (tests/js/middle-truncate-parity.mjs writes it, and middle-truncate.test.mjs fails when it is
// stale), and the PHP must return the same parts. Then the shown string, the Str macro, and the regex fallback for graphemes. No database, no HTTP kernel.
use Illuminate\Support\Str;
use Tey\Kit\Support\MiddleTruncate;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function truncateCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

$cases = json_decode((string) file_get_contents(__DIR__.'/fixtures/middle-truncate/parity.json'), true, flags: JSON_THROW_ON_ERROR);
truncateCheck(count($cases) > 1000, 'the parity corpus is present ('.count($cases).' cases)');
$failures = [];
foreach ($cases as $case) {
    $actual = MiddleTruncate::parts($case['value'], $case['width'], $case['kind'], $case['minTail']);
    if ($actual !== $case['expected']) {
        $failures[] = json_encode([$case['value'], $case['width'], $case['kind'], $case['minTail'], 'ts' => $case['expected'], 'php' => $actual], JSON_UNESCAPED_UNICODE);
    }
}
foreach (array_slice($failures, 0, 10) as $failure) {
    echo 'FAIL '.$failure."\n";
}
truncateCheck($failures === [], 'every case matches middleTruncate.ts ('.count($failures).' differ)');

$label = 'A-8377 · 2009 Chevrolet Silverado C1500 LT';
truncateCheck(MiddleTruncate::cut($label, 100) === $label, 'a label that fits is returned whole');
$cut = MiddleTruncate::cut($label, 30);
truncateCheck(mb_strlen($cut) === 30 && str_starts_with($cut, 'A-8377') && str_ends_with($cut, 'LT') && str_contains($cut, '…'), "the label at 30 keeps its start and end: {$cut}");
truncateCheck(MiddleTruncate::cut('https://example.com/search/results?q=a+very+long+query', 30) === 'https://example.co…h/results?…', 'a URL drops its query first');
truncateCheck(MiddleTruncate::cut('paraframe-brand-guidelines-2026-final.pdf', 16) === 'parafram…nal.pdf', 'a file keeps its extension');

try {
    MiddleTruncate::cut($label, 10, 'words');
    throw new RuntimeException('an unknown kind was not refused');
} catch (InvalidArgumentException) {
    truncateCheck(true, 'an unknown kind is refused');
}

truncateCheck(str_contains((string) file_get_contents(dirname(__DIR__).'/src/KitServiceProvider.php'), 'MiddleTruncate::macro();'), 'KitServiceProvider registers the macro on boot');
Str::flushMacros();
MiddleTruncate::macro();
truncateCheck(Str::middleTruncate($label, 30) === $cut, 'Str::middleTruncate is MiddleTruncate::cut');
$path = 'src/a/very/long/folder/name/file.ts';
truncateCheck(Str::middleTruncate($path, 20, kind: 'path', minTail: 4) === MiddleTruncate::cut($path, 20, 'path', 4), 'the macro takes kind and minTail by name');
Str::flushMacros();
Str::macro('middleTruncate', fn (): string => 'host');
MiddleTruncate::macro();
truncateCheck(Str::middleTruncate($label, 30) === 'host', "a host's own middleTruncate macro is kept");

preg_match_all('/\X/u', 'Team 👩‍👩‍👧‍👦 🇨🇦 é', $regex);
truncateCheck($regex[0] === MiddleTruncate::graphemes('Team 👩‍👩‍👧‍👦 🇨🇦 é'), 'the /\X/u fallback splits graphemes as intl does');
