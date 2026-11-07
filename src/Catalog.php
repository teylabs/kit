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
        'core' => 'sha256:c85e3904292ad9c41d80494ce1ad3cbcd2426f5f36fc0b46ae6a1f553bae42e9',
        'workspaces' => 'sha256:56912cc3486c206ac1167b2c3d4995407c80052779254bab3777782a87e6eafd',
        'access' => 'sha256:98af2e26dd12f50c7e65dcf10e9692afdbc611248571e12d379e0cc7405a2de6',
        'settings' => 'sha256:d36ca148b459d0f516a67652fa57a0294830baa77ec5164d7e026ff4373288cb',
        'shell' => 'sha256:88b159b6e1f2510400ecb31c6b759744eaeeceaf7bcf73650a6bb123d1d50450',
        'session-ui' => 'sha256:2ebf2db2856c3d783fef8307bdc8e0af470cb7e218167ec45ad1f2e8e4b09532',
        'workspace-ui' => 'sha256:a986739cdae6e8d4d40229efd3e3c221fe2fa446f33b21103ada83c8a61d117e',
        'mcp' => 'sha256:c617f5152d9fd76b13e43e20f7ce257951556b623b76069aafcfd4a97ed69ffe',
        'notes' => 'sha256:cc6e2adb297d85cb55ac33d2a414635610e813a292970cc7bb340bcba857bb95',
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
