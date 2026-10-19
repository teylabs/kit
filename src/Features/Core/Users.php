<?php

namespace Tey\Kit\Features\Core;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** The host's user model: `kit.models.user`, else the `users` auth provider's model. */
final class Users
{
    /** @return class-string<Model> */
    public static function model(): string
    {
        $model = config('kit.models.user') ?? config('auth.providers.users.model');
        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new RuntimeException('kit.models.user is not a model class.');
        }

        return $model;
    }

    public static function is(mixed $user): bool
    {
        return $user instanceof (self::model());
    }

    public static function find(int $id): ?Model
    {
        return self::model()::query()->find($id);
    }
}
