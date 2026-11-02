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
        'core' => 'sha256:dc02fc7663a8ab02c5f830deb59fad2ee81bca22a46627ee224be3be5c36f634',
        'workspaces' => 'sha256:56912cc3486c206ac1167b2c3d4995407c80052779254bab3777782a87e6eafd',
        'access' => 'sha256:98af2e26dd12f50c7e65dcf10e9692afdbc611248571e12d379e0cc7405a2de6',
        'settings' => 'sha256:37ad737898994d5024915685418351d6be31f84a93a06a60b62036a1739a6eba',
        'shell' => 'sha256:c7036fbff6410eab53f2b5285e0a8a2a62b5d891dc0eb7774722c4d311578b21',
        'session-ui' => 'sha256:3ef412e31f4ca4b43657bbbedd35d9aeee6564ffd10bc27225cbd25b671eba7e',
        'workspace-ui' => 'sha256:866d8158016934115917af2d27d5b3765fc117c2f037a98f1d7ce0931418510c',
        'mcp' => 'sha256:760d2e0a62e9534a0d89323b27bc4ae1de1cedb4b17fba4bffcb6441efe1d924',
        'notes' => 'sha256:cc6e2adb297d85cb55ac33d2a414635610e813a292970cc7bb340bcba857bb95',
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
