<?php

// Fails a pull request whose CHANGELOG.md breaks the release notes: `## Unreleased` must be the top section and appear at most once, and every released
// `## vX.Y.Z` section the base branch has must be byte-for-byte the same. A merge that splices lines under a released heading fails here. A release (a new
// `## vX.Y.Z` above every released section, in place of `## Unreleased`) and a change that leaves CHANGELOG.md alone both pass.
//   php bin/changelog-check.php <base CHANGELOG.md> [<head CHANGELOG.md>]
// CI: git fetch --depth=1 origin "$GITHUB_BASE_REF" && git show FETCH_HEAD:CHANGELOG.md > /tmp/base-changelog.md && php bin/changelog-check.php /tmp/base-changelog.md

/** @return list<array{heading: string, body: string}> each `## ` section in order, its heading line and everything up to the next one */
function changelogSections(string $markdown): array
{
    $sections = [];
    foreach (preg_split('/^(?=## )/m', $markdown) ?: [] as $chunk) {
        if (str_starts_with($chunk, '## ')) {
            $sections[] = ['heading' => rtrim(strtok($chunk, "\n")), 'body' => $chunk];
        }
    }

    return $sections;
}

/** @return list<string> what is wrong with $head against $base; empty when it passes */
function changelogProblems(string $base, string $head): array
{
    if ($head === $base) {
        return [];
    }
    $problems = [];
    $sections = changelogSections($head);
    $released = fn (array $section): bool => (bool) preg_match('/^## v\d+\.\d+\.\d+\b/', $section['heading']);
    $unreleased = array_values(array_filter($sections, fn (array $section): bool => $section['heading'] === '## Unreleased'));
    if (count($unreleased) > 1) {
        $problems[] = 'CHANGELOG.md has '.count($unreleased).' "## Unreleased" sections; keep one, at the top.';
    }
    $baseReleased = [];
    foreach (changelogSections($base) as $section) {
        if ($released($section)) {
            $baseReleased[$section['heading']] = $section['body'];
        }
    }
    $newReleases = 0;
    $seenOld = false;
    foreach ($sections as $section) {
        if (! $released($section)) {
            continue;
        }
        if (isset($baseReleased[$section['heading']])) {
            $seenOld = true;
            if ($section['body'] !== $baseReleased[$section['heading']]) {
                $problems[] = $section['heading'].' differs from the base branch. Released notes stay as released: put new lines under "## Unreleased".';
            }
        } elseif ($seenOld) {
            $problems[] = $section['heading'].' is not on the base branch and sits below a released section; a new release goes at the top.';
        } else {
            $newReleases++;
        }
    }
    foreach (array_diff_key($baseReleased, array_flip(array_column(array_filter($sections, $released), 'heading'))) as $heading => $_) {
        $problems[] = $heading.' is on the base branch but missing here.';
    }
    $top = $sections[0]['heading'] ?? null;
    if ($top !== '## Unreleased' && $newReleases === 0) {
        $problems[] = 'The top section is "'.($top ?? '(none)').'", not "## Unreleased". Unreleased changes go under "## Unreleased" at the top.';
    }

    return $problems;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (! isset($argv[1]) || ! is_file($argv[1])) {
        fwrite(STDERR, "Usage: php bin/changelog-check.php <base CHANGELOG.md> [<head CHANGELOG.md>]\n");
        exit(2);
    }
    $problems = changelogProblems((string) file_get_contents($argv[1]), (string) file_get_contents($argv[2] ?? dirname(__DIR__).'/CHANGELOG.md'));
    foreach ($problems as $problem) {
        fwrite(STDERR, $problem."\n");
    }
    if ($problems !== []) {
        exit(1);
    }
    echo "CHANGELOG.md: \"## Unreleased\" on top and released sections unchanged.\n";
}
