<?php

namespace Tey\Kit\Features\Core\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The rows a bulk action was asked to act on: the request's `ids[]`, resolved inside the scope the handler passes (the same tenant- and policy-scoped query the
 * index lists). It fails closed: no ids, more than the cap, a malformed id or any id the scope does not contain refuses the whole request (422), so a bulk action
 * never acts on part of a selection or on a row the viewer cannot see.
 */
final class TableSelection
{
    public const int MAX = 100;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $scope
     * @return Collection<int, TModel>
     */
    public static function from(Request $request, Builder $scope, int $max = self::MAX): Collection
    {
        $ids = $request->input('ids');
        if (! is_array($ids) || $ids === [] || count($ids) > $max || ! array_is_list($ids)) {
            throw new HttpException(422, 'Select between 1 and '.$max.' rows.');
        }
        foreach ($ids as $id) {
            if (! (is_int($id) || (is_string($id) && $id !== '' && strlen($id) <= 64))) {
                throw new HttpException(422, 'A selected row id is not valid.');
            }
        }
        $ids = array_values(array_unique(array_map('strval', $ids)));
        $models = (clone $scope)->whereKey($ids)->get();
        if ($models->count() !== count($ids)) {
            throw new HttpException(422, 'A selected row is not available.');
        }

        return $models;
    }
}
