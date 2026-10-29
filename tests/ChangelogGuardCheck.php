<?php

// bin/changelog-check.php: the release-notes guard CI runs on every pull request, checked against made-up base and head changelogs.
require dirname(__DIR__).'/bin/changelog-check.php';

function changelogGuardCheck(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

$released = "## v0.2.0\n\n### Added\n\n- Two.\n\n## v0.1.0\n\n### Added\n\n- One.\n";
$base = "# Changelog\n\n## Unreleased\n\n### Fixed\n\n- Main's fix.\n\n".$released;
$fails = fn (string $head, string $needle): bool => array_filter(changelogProblems($base, $head), fn (string $problem): bool => str_contains($problem, $needle)) !== [];

try {
    changelogGuardCheck(changelogProblems($base, $base) === [], 'an unchanged changelog passes');
    changelogGuardCheck(changelogProblems($base, "# Changelog\n\n## Unreleased\n\n### Fixed\n\n- Main's fix.\n- Mine.\n\n".$released) === [], 'a line added under "## Unreleased" passes');
    changelogGuardCheck(changelogProblems($base, "# Changelog\n\n## v0.3.0\n\n### Fixed\n\n- Main's fix.\n\n".$released) === [], 'a release (a new version at the top in place of "## Unreleased") passes');
    changelogGuardCheck(changelogProblems("# Changelog\n\n".$released, "# Changelog\n\n## Unreleased\n\n### Added\n\n- New.\n\n".$released) === [], 'adding "## Unreleased" above the released notes passes');
    changelogGuardCheck($fails("# Changelog\n\n## Unreleased\n\n- Main's fix.\n\n## v0.2.0\n\n### Added\n\n- Two.\n- Mine, spliced in.\n\n## v0.1.0\n\n### Added\n\n- One.\n", '## v0.2.0 differs from the base branch'), 'a line spliced under a released heading fails');
    changelogGuardCheck($fails("# Changelog\n\n".$released."\n### Added\n\n- Mine.\n", 'not "## Unreleased"') || $fails("# Changelog\n\n".$released."\n### Added\n\n- Mine.\n", 'differs from the base branch'), 'losing "## Unreleased" with changes below a released heading fails');
    changelogGuardCheck($fails("# Changelog\n\n## v0.2.0\n\n### Added\n\n- Two.\n\n## Unreleased\n\n- Mine.\n\n## v0.1.0\n\n### Added\n\n- One.\n", 'not "## Unreleased"'), '"## Unreleased" below a released section fails');
    // The splice a merge from a freshly released main makes: the release renamed "## Unreleased" to "## v0.3.0", and git put the
    // feature's line under that released heading, with no "## Unreleased" left.
    $releasedBase = "# Changelog\n\n## v0.3.0\n\n### Fixed\n\n- Main's fix.\n\n".$released;
    $splice = array_filter(changelogProblems($releasedBase, "# Changelog\n\n## v0.3.0\n\n### Fixed\n\n- Main's fix.\n- Mine, spliced in.\n\n".$released), fn (string $problem): bool => str_contains($problem, '## v0.3.0'));
    changelogGuardCheck($splice !== [], 'a feature line spliced into the section a release just renamed fails, naming that section');
    changelogGuardCheck(changelogProblems($releasedBase, "# Changelog\n\n## Unreleased\n\n### Fixed\n\n- Mine.\n\n## v0.3.0\n\n### Fixed\n\n- Main's fix.\n\n".$released) === [], 'the same feature under a fresh "## Unreleased" above the new release passes');
    changelogGuardCheck($fails("# Changelog\n\n## Unreleased\n\n- Mine.\n\n## Unreleased\n\n- Main's fix.\n\n".$released, '2 "## Unreleased" sections'), 'two "## Unreleased" sections fail');
    changelogGuardCheck($fails("# Changelog\n\n## Unreleased\n\n- Mine.\n\n## v0.2.0\n\n### Added\n\n- Two.\n", '## v0.1.0 is on the base branch but missing here'), 'a released section dropped fails');
    changelogGuardCheck($fails("# Changelog\n\n## Unreleased\n\n- Mine.\n\n".$released."\n## v0.1.5\n\n- Odd.\n", 'sits below a released section'), 'a new version below the released notes fails');
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
echo "Changelog guard checks on made-up changelogs; CI runs bin/changelog-check.php against the pull request's base branch.\n";
