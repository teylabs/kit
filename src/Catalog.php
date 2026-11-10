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
        'core' => 'sha256:4a06105ba7fb1bedf3c8df147f49199c16195f13983ec89f57289c5a440b24dc',
        'workspaces' => 'sha256:bbf33a40f7a70cd24b99d270cbca71f088265243e03d679ba35b588eb50a99f4',
        'access' => 'sha256:98af2e26dd12f50c7e65dcf10e9692afdbc611248571e12d379e0cc7405a2de6',
        'settings' => 'sha256:27a85269fb2355b41800758bcd012fd505f2eb7dc6576a3fa311b6da2ba6bfc4',
        'shell' => 'sha256:d2a515a5983d078fe3a7e91075e43a1a9760bbed3c68c50b30311334eccff2ea',
        'session-ui' => 'sha256:0a7ba81253a7eed39e5965e02f6c784852c45b04a11fd35c49cba0ef6bb19e87',
        'workspace-ui' => 'sha256:5a68eebb533f204187b1477e57b0f3ec8d824ed5997c0feb065dd45e9cb5a192',
        'mcp' => 'sha256:f1dee960991cc0cc3cdd9dd51f65be5934f4c06d6d648cf0974131bd3c4eb002',
        'notes' => 'sha256:44c7588a11b6765295382a2b8e3ab871ed698e0aa0d30e478c3c6305af6a31bd',
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
