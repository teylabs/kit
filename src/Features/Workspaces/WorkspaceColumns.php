<?php

namespace Tey\Kit\Features\Workspaces;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Whether a workspace table has a column (IsKitWorkspace's `admitted` and `deleting_at`), asked of the database once a day rather than once per request (FPM drops
 * statics after each one). Two layers. The request's own answers, in this scoped instance: a new request (an Octane request, a queue worker's next job) starts
 * empty, and a request asks the cache once per column. And the shared cache, which holds only the schema fact: one key per column, true or false, for a day
 * (a key with a TTL can be evicted under a volatile-* policy). Concurrent requests that learn different columns each write their own key, so neither loses
 * the other's; two that learn the same one write the same answer. The keys carry a generation (`KEY`) that `migrate` forgets, so a column a migration adds
 * is seen at once; one added any other way is seen within the day. A table that is not there yet caches nothing: it is asked again, so its columns are seen
 * as soon as it is created.
 */
final class WorkspaceColumns
{
    /** The cache key of the generation the column keys carry; `migrate` forgets it, and with it every column answer. */
    public const string KEY = 'kit-workspace-columns';

    /** How long a column answer, and the generation, are cached, in seconds: a day. */
    public const int TTL = 86400;

    /** @var array<string, bool> this request's answers, by connection|table|column */
    private array $known = [];

    private ?string $generation = null;

    public function has(?string $connection, string $table, string $column): bool
    {
        $name = ($connection ?? '').'|'.$table.'|'.$column;
        if (array_key_exists($name, $this->known)) {
            return $this->known[$name];
        }
        try {
            $key = self::KEY.':'.$this->generation().':'.$name;
            $fact = Cache::get($key);
            if (! is_bool($fact)) {
                $schema = Schema::connection($connection);
                $fact = $schema->hasColumn($table, $column);
                if (! $fact && ! $schema->hasTable($table)) {
                    return false;
                }
                Cache::put($key, $fact, self::TTL);
            }

            return $this->known[$name] = $fact;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Forgets every answer, this request's and the shared ones (a new generation): any migration, up or down, may change the columns. */
    public function forget(): void
    {
        $this->known = [];
        $this->generation = null;
        Cache::forget(self::KEY);
    }

    /** The generation the column keys carry: one per `migrate`, agreed on by `add()`, so two requests after a migrate share one. */
    private function generation(): string
    {
        if ($this->generation !== null) {
            return $this->generation;
        }
        $generation = Cache::get(self::KEY);
        if (! is_string($generation)) {
            $generation = Str::random(16);
            $generation = Cache::add(self::KEY, $generation, self::TTL) ? $generation : (string) (Cache::get(self::KEY) ?? $generation);
        }

        return $this->generation = $generation;
    }
}
