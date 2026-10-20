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
        'core' => 'sha256:ad6c75ef93b1490f31e75c4a0b5f4e9f9e0e498ba56a8473dd45990cb70a3e6e',
        'workspaces' => 'sha256:5fdee51c2c53b689254d78c90427ba9b9b026761e08cbd2c39bad204751e910a',
        'access' => 'sha256:c3725a907314a56a96b09cfc33ab7c69a098c761460fb49bf1b07df542c1f78b',
        'settings' => 'sha256:8708481336deb347129f53eccc855cac90f73bfa00451d07ef5aa428f3416396',
        'session-ui' => 'sha256:a64fbf151ce3852230fe06fcc7d484d75ddeae21232c815b80e7825e90ea2874',
        'mcp' => 'sha256:3808a17de02db6c1644b9598452052b895ba7a752fa5e2dab237528b587ce574',
        'notes' => 'sha256:52ac28b68c0d91c6cd4b7e8de315eaefc10c9cab319b3b06b09b479abade9446',
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
