<?php

namespace Tey\Kit\Features\Settings\Setup;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Data\Action;

/**
 * One row of Settings → Setup. `state`: `ok`; `info` (nothing to do, but worth knowing); `warning`; `failing`; `missing` (not set up yet); `held` (set
 * up, and deliberately held back: mail only to an allow-list, a domain waiting for DNS); `overridden` (an environment variable decides it); `manual`
 * (a step only a person can do, at the link); `na` (only matters in production, and this app is local or iterating). `fix` says what to do, `link` where; `action` is something the viewer can run from the row.
 */
#[TypeScript]
final class SetupRow extends Data
{
    public const STATES = ['ok', 'info', 'warning', 'failing', 'missing', 'held', 'overridden', 'manual', 'na'];

    /** @param  ?array{label: string, url: string}  $link */
    public function __construct(
        public string $key,
        public string $group,
        public string $label,
        #[LiteralTypeScriptType("'ok' | 'info' | 'warning' | 'failing' | 'missing' | 'held' | 'overridden' | 'manual' | 'na'")]
        public string $state,
        public string $detail,
        public ?string $fix = null,
        #[LiteralTypeScriptType('{ label: string; url: string } | null')]
        public ?array $link = null,
        public ?Action $action = null,
    ) {
        if (! in_array($state, self::STATES, true)) {
            throw new \RuntimeException('Unknown setup row state: '.$state);
        }
    }

    /** A row whose key and group the page fills in from its registration. */
    public static function of(string $label, string $state, string $detail, ?string $fix = null, ?array $link = null, ?Action $action = null): self
    {
        return new self('', '', $label, $state, $detail, $fix, $link, $action);
    }

    /** A production-only row on a local or iterating app: neutral, never red. */
    public static function na(string $label, string $detail): self
    {
        return self::of($label, 'na', $detail);
    }

    public function placed(string $key, string $group): self
    {
        $copy = clone $this;
        $copy->key = $key;
        $copy->group = $group;

        return $copy;
    }

    /** @return array{label: string, url: string} */
    /** A relative `$url` resolves from the workspace's root, not from /settings: a settings section is `settings/<key>`. */
    public static function link(string $label, string $url): array
    {
        return ['label' => $label, 'url' => $url];
    }
}
