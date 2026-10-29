<?php

namespace Tey\Kit;

use RuntimeException;

/**
 * A directory of feature manifests and stubs, with the identity each feature must have.
 *
 * The root holds `blueprint.json` and `<feature>/feature.json`. Pins map a feature id to the sha256
 * identity of its manifest and stubs, in canonical order; basePin is the identity of `blueprint.json`.
 * An external catalog is checked exactly as strictly as this package's own.
 */
final readonly class Catalog
{
    /** Selectable features in canonical order, with the pinned identity of each feature's manifest and stubs. */
    private const FEATURES = [
        'core' => 'sha256:9223364e65c3db6bd71c15e5eb7f9633b60aa02f25fbc25dacd1b0622652f59e',
        'workspaces' => 'sha256:a1adff6fe10dab3eeafbdc1d1d325e90741aaf72f9b66fc35d6eaa69cf5d3c22',
        'access' => 'sha256:a179066fa9e5ca2279c2752ff67644f592ec4c5ee37e8cf263c205ddb7526d98',
        'settings' => 'sha256:fb83eb1d9e21b5d579b4cd00cce30401e3c79d0c9f839ddfcf53a77daa815f1e',
        'shell' => 'sha256:8adcf2e2beab1c78454e296180d9ef6a434c0bf8169ae0522ab7ad040f092024',
        'session-ui' => 'sha256:4ebd502fff14e55938fee949ca414f07e65fd3dcc8a0595edb46d67b483b3e95',
        'workspace-ui' => 'sha256:e5f861f9c1494f9199ba417514b6d4e4ba19693669f3ef243aae90aa8ed56950',
        'mcp' => 'sha256:d1a28359e1bee9800fa8ec3bf6d670e60c3b51ab052ca4b748779dd7d8fb36f3',
        'notes' => 'sha256:d373d438e69b7e4699f2cb0b2fdab860d1566506aa62a9ad8119472a46c2f1b2',
    ];

    private const BASE = 'sha256:c513b68d2e13a237a0e9d1bed497ab54496098de3ca03628fc44f21b2921f079';

    /** @param array<string, string> $pins */
    private function __construct(public string $root, public array $pins, public ?string $basePin) {}

    /** @param array<string, string> $pins feature id => sha256 identity */
    public static function at(string $root, array $pins, string $basePin): self
    {
        $resolved = is_link($root) ? false : realpath($root);
        if ($resolved === false || ! is_dir($resolved)) {
            throw new RuntimeException('Missing or linked catalog root.');
        }
        if ($pins === []) {
            throw new RuntimeException('A catalog pins at least one feature.');
        }
        foreach ($pins as $id => $pin) {
            if (! is_string($id) || ! preg_match('/\A[a-z0-9]+(-[a-z0-9]+)*\z/', $id)) {
                throw new RuntimeException('Invalid catalog feature id.');
            }
            if (! is_string($pin) || ! preg_match('/\Asha256:[0-9a-f]{64}\z/', $pin)) {
                throw new RuntimeException('Invalid catalog pin for feature: '.$id);
            }
        }
        if (! preg_match('/\Asha256:[0-9a-f]{64}\z/', $basePin)) {
            throw new RuntimeException('Invalid catalog base pin.');
        }

        return new self($resolved, $pins, $basePin);
    }

    /**
     * This package's own catalog: the starter features (docs/catalog.md). The v0.1 foundation and
     * defaults features are a test fixture (tests/fixtures/v01), not part of it.
     */
    public static function package(): self
    {
        return new self(dirname(__DIR__).'/resources/features', self::FEATURES, self::BASE);
    }

    /** @return list<string> Feature ids in canonical order. */
    public function featureIds(): array
    {
        return array_keys($this->pins);
    }

    public function directory(string $id): string
    {
        if (! isset($this->pins[$id])) {
            throw new RuntimeException('Unknown feature: '.$id);
        }

        return $this->root.'/'.$id;
    }
}
