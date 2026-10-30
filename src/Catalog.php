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
        'core' => 'sha256:c1789bde8b0220b3cc50bda6b66ede3f7d4b79a14d27617c18e8995cb4f61874',
        'workspaces' => 'sha256:56912cc3486c206ac1167b2c3d4995407c80052779254bab3777782a87e6eafd',
        'access' => 'sha256:a179066fa9e5ca2279c2752ff67644f592ec4c5ee37e8cf263c205ddb7526d98',
        'settings' => 'sha256:fb83eb1d9e21b5d579b4cd00cce30401e3c79d0c9f839ddfcf53a77daa815f1e',
        'shell' => 'sha256:0daa7d88a2a08ae3fe2832fc75b392564e05a3e6263cc8da29deaac1f836a5b5',
        'session-ui' => 'sha256:4bb867c5dfdebe375f7c5743527ccaeeaed888e0d3d4f0fc0efbab675ef25154',
        'workspace-ui' => 'sha256:a6ece55802247c45fe579540ad73b6658a521ed5db3b55dffcafaff86496cfdd',
        'mcp' => 'sha256:e35e287934b5e02a9b3683d2f0c98fedddae7b276c510638eacec1fd2a3b7ae0',
        'notes' => 'sha256:1a0455e79979a94149cdd82090cb59364f764a18a66f168669c2f2f862fb551b',
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
