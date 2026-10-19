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
        'core' => 'sha256:63bbc189cc2261d6c5c6a8dde107a8794668218167983f2006e2626c62120ca6',
        'workspaces' => 'sha256:9545bff8c29735b10b592e9bd88c389e795c60f96b9577fd5649722fe4425e19',
        'access' => 'sha256:a9ba9e906cfc0ce0498efed235a078795284f15b403e62ff0ce19d8a2b19edd2',
        'settings' => 'sha256:cb1ddd87ab100fea3b9ac711c02b89d905b53e621744dc69a5584b2fba6fb4ca',
        'session-ui' => 'sha256:62c6138d8b7e7a360c535aea33b14eb94512d151a01e9545c34eb4e4f66eb634',
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
