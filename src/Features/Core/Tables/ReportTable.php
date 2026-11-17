<?php

namespace Tey\Kit\Features\Core\Tables;

use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

/**
 * The server side of a report table drawn by LineItems (docs/line-items.md): computed aggregate rows (not Eloquent models) with their columns and totals,
 * declared once in the controller and passed as one prop (`<LineItems v-bind="report" />`). `kind` is LineItems' (`text`, `number`, `money`, `percent`, `date`);
 * a money column's `currencyKey` names the row field holding that row's currency (else the column's `currency`). Each row keeps only the declared columns and
 * their currency fields, so nothing else on a computed row reaches the page. An unknown kind, a duplicate key or a currency on a non-money column fails here.
 *
 * @implements Arrayable<string, mixed>
 */
final class ReportTable implements Arrayable, JsonSerializable
{
    public const array KINDS = ['text', 'number', 'money', 'percent', 'date'];

    /** @var array<string, array<string, mixed>> */
    private array $columns = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var list<array{label: string, value: string|int|float|null, emphasis?: bool}> */
    private array $totals = [];

    private ?string $caption = null;

    private ?string $empty = null;

    private ?string $rowHeader = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param  'start'|'end'|'center'|null  $align
     * @param  string|null  $currency  a money column's ISO code for every row
     * @param  string|null  $currencyKey  a money column's row field holding that row's ISO code (it wins over `currency`)
     * @param  bool  $sortable  the header sorts the rows in the browser (numeric for number, money and percent; empty values last)
     */
    public function column(string $key, string $label, string $kind = 'text', ?string $align = null, ?string $currency = null, ?string $currencyKey = null, bool $sortable = false, bool $nowrap = false): self
    {
        if (isset($this->columns[$key])) {
            throw new InvalidArgumentException("ReportTable column [{$key}] is declared twice.");
        }
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("ReportTable column [{$key}] has an unknown kind [{$kind}]; use one of ".implode(', ', self::KINDS).'.');
        }
        if ($align !== null && ! in_array($align, ['start', 'end', 'center'], true)) {
            throw new InvalidArgumentException("ReportTable column [{$key}] has an unknown align [{$align}].");
        }
        if (($currency !== null || $currencyKey !== null) && $kind !== 'money') {
            throw new InvalidArgumentException("ReportTable column [{$key}] is not money, so it takes no currency.");
        }
        $this->columns[$key] = array_filter(
            ['key' => $key, 'label' => $label, 'kind' => $kind, 'align' => $align, 'currency' => $currency, 'currencyKey' => $currencyKey, 'sortable' => $sortable ?: null, 'nowrap' => $nowrap ?: null],
            fn (mixed $value): bool => $value !== null,
        );

        return $this;
    }

    /** @param  iterable<array<string, mixed>|Arrayable<string, mixed>|object>  $rows */
    public function rows(iterable $rows): self
    {
        $this->rows = [];
        foreach ($rows as $row) {
            $row = $row instanceof Arrayable ? $row->toArray() : (array) $row;
            $kept = [];
            foreach ($this->columns as $key => $column) {
                $kept[$key] = $row[$key] ?? null;
                if (isset($column['currencyKey'])) {
                    $kept[$column['currencyKey']] = $row[$column['currencyKey']] ?? null;
                }
            }
            $this->rows[] = $kept;
        }

        return $this;
    }

    /** @param  list<array{label: string, value: string|int|float|null, emphasis?: bool}>  $totals */
    public function totals(array $totals): self
    {
        $this->totals = [];
        foreach ($totals as $total) {
            $this->total($total['label'], $total['value'] ?? null, $total['emphasis'] ?? false);
        }

        return $this;
    }

    public function total(string $label, string|int|float|null $value, bool $emphasis = false): self
    {
        $this->totals[] = $emphasis ? ['label' => $label, 'value' => $value, 'emphasis' => true] : ['label' => $label, 'value' => $value];

        return $this;
    }

    public function caption(string $caption): self
    {
        $this->caption = $caption;

        return $this;
    }

    /** The line shown when there are no rows. */
    public function empty(string $empty): self
    {
        $this->empty = $empty;

        return $this;
    }

    /** A column whose cells are row headers (`<th scope="row">`). */
    public function rowHeader(string $key): self
    {
        if (! isset($this->columns[$key])) {
            throw new InvalidArgumentException("ReportTable rowHeader [{$key}] is not a declared column.");
        }
        $this->rowHeader = $key;

        return $this;
    }

    /** @return array{columns: list<array<string, mixed>>, rows: list<array<string, mixed>>, totals: list<array<string, mixed>>, caption?: string, empty?: string, rowHeader?: string} */
    public function toArray(): array
    {
        return array_filter(
            ['columns' => array_values($this->columns), 'rows' => $this->rows, 'totals' => $this->totals, 'caption' => $this->caption, 'empty' => $this->empty, 'rowHeader' => $this->rowHeader],
            fn (mixed $value): bool => $value !== null,
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
