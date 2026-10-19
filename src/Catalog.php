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
        'core' => 'sha256:99c9e51b709a11508bfe639006883a6b6acf9bcecd10f647c3ec355a99c3e44a',
        'workspaces' => 'sha256:f34dd80040550d45b27b534652c5171f57e63dbb547bca0d93c012d71954fb23',
        'access' => 'sha256:f157033cd158eb0ee0ab945aa1f8b18041b845afbec55f520efb8a96e931b698',
        'settings' => 'sha256:3ff709b98359375794499e9eba9a7a73d8b8089281215d4bebd2ad71a9d93b1f',
        'session-ui' => 'sha256:c72675da6eb21eb4eca0cb9a251931e356d53d348627d96a0d914e6b330f9f89',
        'mcp' => 'sha256:40640b990cb23857b1076916c310615d03faf09e927e9a34c17fc431b5f6f7e2',
        'notes' => 'sha256:804cb514b4a4911892b5c6b8cbc4580b57fe7b734451e53d21d88ba05c9783b3',
    ];

    private const BASE = 'sha256:32b571f7cdd2b12d698d04a76a847a5561d96a60f051129dce52832112995fa7';

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
