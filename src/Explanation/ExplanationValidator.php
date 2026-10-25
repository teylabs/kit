<?php

declare(strict_types=1);

namespace Tey\Kit\Explanation;

use InvalidArgumentException;
use SplObjectStorage;

/** Structural validation only. The host verifies arithmetic, policy and authorization. */
final class ExplanationValidator
{
    public static function validate(ExplanationDocument $document): void
    {
        self::require($document->schemaVersion === '1', 'Unsupported explanation version.');
        $seen = [];
        $count = 0;
        $active = new SplObjectStorage;
        foreach ($document->roots as $node) {
            self::node($node, 1, $seen, $count, $active);
        }
        self::require(strlen(json_encode($document->toArray(), JSON_THROW_ON_ERROR)) <= 262144, 'Explanation exceeds 256 KiB.');
    }

    private static function node(ExplanationNode $node, int $depth, array &$seen, int &$count, SplObjectStorage $active): void
    {
        self::require($depth <= 16 && ++$count <= 1000, 'Explanation exceeds display budget.');
        self::require(! $active->offsetExists($node), 'Recursive explanation cycle.');
        self::require($node->schemaVersion === '1' && trim($node->id) !== '' && ! isset($seen[$node->id]), 'Invalid version or duplicate node ID.');
        $seen[$node->id] = true;
        $active->offsetSet($node);
        self::value($node->value);
        if ($node->unrounded !== null) {
            self::decimal($node->unrounded);
        }
        $keys = [];
        foreach ($node->inputs as $input) {
            self::require($input instanceof ExplanationInput && ! isset($keys[$input->key]), 'Invalid or duplicate input.');
            $keys[$input->key] = true;
            self::value($input->value);
            self::decimal($input->exact);
            self::source($input->source);
            self::require($input->value->currency === $node->value->currency && $input->value->unit === $node->value->unit, 'Incompatible units; adapt conversions in the host.');
        }
        foreach ($node->children as $child) {
            self::require($child instanceof ExplanationNode && ! isset($keys[$child->id]), 'Invalid or duplicate operand.');
            $keys[$child->id] = true;
            self::require($child->value->currency === $node->value->currency && $child->value->unit === $node->value->unit, 'Incompatible child units.');
            self::node($child, $depth + 1, $seen, $count, $active);
        }
        $op = $node->operation;
        if ($node->kind !== ExplanationKind::Input) {
            self::require($op !== null && trim($op->name) !== '' && trim($op->version) !== '', 'An operation name and version are required.');
        }
        if ($op) {
            foreach ([...$op->operandOrder, ...$op->includedKeys] as $key) {
                self::require(isset($keys[$key]), 'Dangling operand key.');
            }
            self::require(count(array_unique($op->operandOrder)) === count($op->operandOrder) && count(array_unique($op->includedKeys)) === count($op->includedKeys), 'Duplicate operation keys.');
            if ($op->divisor !== null) {
                self::decimal($op->divisor);
                self::require(! str_starts_with($op->divisor, '-') && preg_match('/[1-9]/', $op->divisor), 'Divisor must be positive.');
            }
            if ($op->exactFraction) {
                self::decimal($op->exactFraction->numerator);
                self::decimal($op->exactFraction->denominator);
                self::require(! str_starts_with($op->exactFraction->denominator, '-') && preg_match('/[1-9]/', $op->exactFraction->denominator), 'Invalid denominator.');
            }
            if ($op->bounds) {
                self::decimal($op->bounds->lower);
                self::decimal($op->bounds->upper);
                self::require(trim($op->bounds->selected) !== '', 'A clamp branch is required.');
            }
        }
        match ($node->kind) {
            ExplanationKind::Mean => self::require($op?->divisor !== null && count($op->includedKeys) > 0, 'Mean needs a divisor and inclusion list.'),
            ExplanationKind::Percent => self::require($op?->exactFraction !== null && count($op->operandOrder) > 0, 'Percent needs a base and exact fraction.'),
            ExplanationKind::Difference => self::require(count($op?->operandOrder ?? []) >= 2, 'Difference needs ordered operands.'),
            ExplanationKind::Clamp => self::require($op?->bounds !== null, 'Clamp needs bounds and a selected branch.'),
            ExplanationKind::Lookup => self::require($node->rule?->boundaries !== null && trim($node->rule?->version ?? '') !== '' && trim($node->rule?->selection ?? '') !== '', 'Lookup needs version, boundaries and selection.'),
            ExplanationKind::Custom => self::require(count($op?->operandOrder ?? []) > 0, 'Custom needs declared operands.'),
            ExplanationKind::Rounding => self::require($node->rounding !== null, 'Rounding needs its policy.'),
            default => null,
        };
        if ($node->rule) {
            self::require(trim($node->rule->version) !== '', 'Rule version is required.');
            if ($node->rule->rate) {
                self::decimal($node->rule->rate->exact);
            }
            if ($node->rule->boundaries) {
                foreach ([$node->rule->boundaries->lower, $node->rule->boundaries->upper] as $bound) {
                    if ($bound !== null) {
                        self::decimal($bound);
                    }
                }
            }
        }
        if ($node->rounding) {
            self::require(trim($node->rounding->policy) !== '' && $node->rounding->place >= 0, 'Invalid rounding policy.');
            self::decimal($node->rounding->before);
            self::decimal($node->rounding->after);
        }
        if ($node->allocation) {
            foreach (['exactShareMinor', 'baseMinor', 'remainderMinor', 'allocatedMinor', 'adjustmentMinor'] as $field) {
                self::decimal($node->allocation->$field);
            }
            self::require(trim($node->allocation->policy) !== '' && trim($node->allocation->tieBreak) !== '', 'Allocation needs policy and tie-break.');
        }
        foreach ($node->exclusions as $excluded) {
            self::require(trim($excluded->reason) !== '', 'Exclusion needs a reason.');
            if ($excluded->source) {
                self::source($excluded->source);
            }
        }
        $active->offsetUnset($node);
    }

    private static function value(ExplanationValue $value): void
    {
        self::require(preg_match('/^-?(0|[1-9][0-9]*)$/D', $value->minor) && $value->minor !== '-0', 'Invalid minor-unit string.');
        self::require($value->scale >= 0 && $value->scale <= 20 && (($value->currency !== null) xor ($value->unit !== null)), 'A value needs a scale and exactly one currency or unit.');
    }

    private static function source(ExplanationSource $source): void
    {
        if ($source->kind === SourceKind::Manual) {
            self::require(trim($source->reason ?? '') !== '', 'Manual source needs a reason.');
        }
        if ($source->kind === SourceKind::Config) {
            self::require(trim($source->version ?? '') !== '', 'Config source needs a version.');
        }
        if ($source->link) {
            self::require((bool) preg_match('~^(https?://|/(?!/)|#)~', $source->link->url), 'Unsafe source URL.');
        }
    }

    private static function decimal(string $value): void
    {
        self::require((bool) preg_match('/^-?(0|[1-9][0-9]*)(\.[0-9]+)?$/D', $value), 'Invalid exact decimal.');
    }

    private static function require(bool $ok, string $message): void
    {
        if (! $ok) {
            throw new InvalidArgumentException($message);
        }
    }
}
