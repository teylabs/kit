<?php

// The pickers' server rules (src/Validation): IsoDate and IsoRange through Laravel's real validator; synthetic values, no database, request or host.
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Tey\Kit\Validation\IsoDate;
use Tey\Kit\Validation\IsoRange;

$loader = require $argv[1];
$loader->addPsr4('Tey\\Kit\\', dirname(__DIR__).'/src', true);

function isoCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo 'PASS '.$message."\n";
}

$validator = new Factory(new Translator(new ArrayLoader, 'en'));
$errors = fn (array $data, array $rules): array => $validator->make($data, $rules)->errors()->toArray();
$passes = fn (mixed $value, IsoDate|IsoRange $rule): bool => $errors(['v' => $value], ['v' => [$rule]]) === [];

foreach (['2028-02-29', '2026-12-31', '0001-01-01'] as $date) {
    isoCheck($passes($date, IsoDate::date()), "a real date passes: {$date}");
}
foreach (['2026-02-29', '2026-13-01', '2026-1-01', '2026-07-15T00:00:00Z', '07/15/2026', 20260715, null] as $date) {
    isoCheck(! $passes($date, IsoDate::date()), 'not a date fails: '.var_export($date, true));
}
isoCheck($passes('2026-07', IsoDate::month()) && ! $passes('2026-00', IsoDate::month()) && ! $passes('2026-07-01', IsoDate::month()), 'a month is YYYY-MM with a real month');
isoCheck($passes('2026', IsoDate::year()) && ! $passes('0000', IsoDate::year()) && ! $passes('26', IsoDate::year()), 'a year is four digits, from 0001');
isoCheck($passes('00:00', IsoDate::time()) && $passes('23:59', IsoDate::time()) && ! $passes('24:00', IsoDate::time()) && ! $passes('9:30', IsoDate::time()) && ! $passes('09:30:00', IsoDate::time()), 'a time is a 24-hour HH:MM');
isoCheck($passes('2026-07-15T09:30:00-03:00', IsoDate::instant()) && $passes('2026-07-15T12:30Z', IsoDate::instant()) && $passes('2026-07-15T12:30:00.250+05:30', IsoDate::instant()), 'an instant carries its offset');
isoCheck(! $passes('2026-07-15T09:30:00', IsoDate::instant()) && ! $passes('2026-07-15 09:30:00-03:00', IsoDate::instant()) && ! $passes('2026-02-30T09:30:00Z', IsoDate::instant()), 'an instant without an offset, or not RFC 3339, fails');

// Bounds are inclusive; a wall value compares as written, an instant as the moment it names.
isoCheck($passes('2026-01-01', IsoDate::date('2026-01-01', '2026-12-31')) && ! $passes('2025-12-31', IsoDate::date('2026-01-01')) && ! $passes('2027-01-01', IsoDate::date(null, '2026-12-31')), 'date bounds are inclusive');
isoCheck($passes('09:00', IsoDate::time('09:00', '17:00')) && ! $passes('17:01', IsoDate::time('09:00', '17:00')), 'time bounds are inclusive');
isoCheck($passes('2026-07-15T09:30:00-03:00', IsoDate::instant(null, '2026-07-15T12:30:00Z')) && ! $passes('2026-07-15T09:31:00-03:00', IsoDate::instant(null, '2026-07-15T12:30:00Z')), 'instant bounds compare the moment, across offsets');
try {
    IsoDate::date('2026-02-30');
    isoCheck(false, 'a bad bound is refused');
} catch (InvalidArgumentException) {
    isoCheck(true, 'a bad bound is refused when the rule is built');
}

// A date is never converted: the server's zone does not change what passes or how it compares.
foreach (['Pacific/Kiritimati', 'Pacific/Pago_Pago', 'America/St_Johns'] as $zone) {
    date_default_timezone_set($zone);
    isoCheck($passes('2026-03-08', IsoDate::date('2026-03-08', '2026-03-08')), "a date is the same date with the server in {$zone}");
}
date_default_timezone_set('UTC');

// Ranges: {start, end}; blank is empty, half-filled and reversed fail on the range's own attribute.
isoCheck($passes(['start' => '2026-01-01', 'end' => '2026-01-31'], IsoRange::dates()), 'a date range passes');
isoCheck($passes(['start' => '2026-01-01', 'end' => '2026-01-01'], IsoRange::dates()), 'a one-day range passes');
isoCheck($passes(['start' => '', 'end' => ''], IsoRange::dates()) && $passes(['start' => null, 'end' => null], IsoRange::dates()), 'an empty range passes (required decides)');
$half = $errors(['v' => ['start' => '2026-01-01', 'end' => '']], ['v' => [IsoRange::dates()]]);
isoCheck(array_keys($half) === ['v'] && str_contains($half['v'][0], 'both'), 'a half-filled range fails on the range');
$reversed = $errors(['v' => ['start' => '2026-02-01', 'end' => '2026-01-01']], ['v' => [IsoRange::dates()]]);
isoCheck(array_keys($reversed) === ['v'] && str_contains($reversed['v'][0], 'before'), 'a reversed range fails on the range');
isoCheck(! $passes(['start' => '17:00', 'end' => '09:00'], IsoRange::times()) && $passes(['start' => '09:00', 'end' => '17:00'], IsoRange::times()), 'a time range keeps its order');
isoCheck(! $passes(['start' => '2026-01-01', 'end' => '2026-02-30'], IsoRange::dates()), 'a range end must be a real date');
isoCheck(! $passes('2026-01-01', IsoRange::dates()) && ! $passes(['start' => '2026-01-01', 'end' => '2026-01-02', 'x' => 1], IsoRange::dates()), 'a range is exactly {start, end}');
isoCheck(! $passes(['start' => '2025-12-31', 'end' => '2026-01-02'], IsoRange::dates('2026-01-01')), 'a range end obeys the bounds');

echo "IsoDate: Laravel's validator over synthetic values. No request, form or browser; the pickers' own checks are tests/js/date-input.test.mjs and the Playwright run.\n";
