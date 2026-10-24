<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use Spatie\LaravelData\Data;

final class ExplanationDocument extends Data
{
    /** Reject JSON floats before Laravel Data could coerce one into a string. */
    public static function from(mixed ...$payloads): static
    {
        $check = function (mixed $value) use (&$check): void {
            if (is_float($value)) {
                throw new \InvalidArgumentException('Explanation values must use exact strings.');
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $check($item);
                }
            }
        };
        foreach ($payloads as $payload) {
            $check($payload);
        }

        return parent::from(...$payloads);
    }

    /** @param list<ExplanationNode> $roots */
    public function __construct(public string $schemaVersion, public string $snapshotKey, public string $rulesVersion, public string $locale, public bool $frozen, public array $roots)
    {
        ExplanationValidator::validate($this);
    }
}
