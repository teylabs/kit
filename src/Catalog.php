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
        'core' => 'sha256:409f5d3d2f0cde86d9bb711ad84868233e2ec3c48c706115a6a0845ffb47c6ab',
        'workspaces' => 'sha256:56912cc3486c206ac1167b2c3d4995407c80052779254bab3777782a87e6eafd',
        'access' => 'sha256:98af2e26dd12f50c7e65dcf10e9692afdbc611248571e12d379e0cc7405a2de6',
        'settings' => 'sha256:27a85269fb2355b41800758bcd012fd505f2eb7dc6576a3fa311b6da2ba6bfc4',
        'shell' => 'sha256:fea7c4b03e7fe1a149faed67f7fa6e43fb7925a3c83b51c106cbe55fb7a168b9',
        'session-ui' => 'sha256:0a7ba81253a7eed39e5965e02f6c784852c45b04a11fd35c49cba0ef6bb19e87',
        'workspace-ui' => 'sha256:01054e11c60a077bbc93211d71f1e807077015e97c19665cfb48dfb9d3544895',
        'mcp' => 'sha256:f1dee960991cc0cc3cdd9dd51f65be5934f4c06d6d648cf0974131bd3c4eb002',
        'notes' => 'sha256:2eaae84ac53045a834c2d72b79acfb15f81860cb506cadcd196769f24a317644',
        'finalize' => 'sha256:c121af94b55eeb2b20cef7b60d28b7887c3916e7eda8b7d437c80e2ea8e445d6',
        'deliver' => 'sha256:5004ea8e0f677a408323c39a2a4c0041bf2d0b3749ede70ebd0ecdd01afa032d',
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
