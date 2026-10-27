<?php

namespace Tey\Kit\Features\Core\Tables;

use Spatie\QueryBuilder\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

/** A value outside a filter's declared options: a 400 like a filter the table does not allow, never an empty or unfiltered result. */
final class InvalidFilterValue extends InvalidQuery
{
    public static function make(string $filter, string $value): self
    {
        return new self(Response::HTTP_BAD_REQUEST, "The value `{$value}` is not available for filter `{$filter}`.");
    }
}
