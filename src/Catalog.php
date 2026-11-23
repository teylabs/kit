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
        'core' => 'sha256:110b3565abeb834679e2dc5d52e974cb0754b903747ad9485de4f3fb54b2dc81',
        'workspaces' => 'sha256:b9d980603245c47565b9bf0e49064faf6cb29b9863da5db2be2abe0338e576a4',
        'access' => 'sha256:98af2e26dd12f50c7e65dcf10e9692afdbc611248571e12d379e0cc7405a2de6',
        'settings' => 'sha256:222aa23365087ff10406af69e5481f58b5a9a691059611d51e77ce7898914757',
        'shell' => 'sha256:2a78630af4a8a79f98acc54916042067cdc01348de54f64e3b05904958c94724',
        'session-ui' => 'sha256:ec07b4f151747df409e26868731b0368015b6a6ee9bb6dd08fc9276a68bc5e67',
        'workspace-ui' => 'sha256:472e3ef070d81ddc0c0677c3cb5b68c7a5bda2a98ee67981e2072e2e470957ab',
        'mcp' => 'sha256:d2a355b782f0f4fad876ce5d7fd5939f1c91ee97aa81ea7475907c11267dbe5a',
        'notes' => 'sha256:9c1c18a6c967b6059b7281d803fae1fcd7614ddbabcf07974cd0f9f69c3ebe65',
        'finalize' => 'sha256:c121af94b55eeb2b20cef7b60d28b7887c3916e7eda8b7d437c80e2ea8e445d6',
        'deliver' => 'sha256:261e32989010829837938a479b88fc0dcb29385b69450898b2b2d2fb9483b6ea',
    ];

    private const BASE = 'sha256:596e1701b4bb1fd524fe62978ca1b2d5122f6756cfb0b5146255938545b79e39';

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
