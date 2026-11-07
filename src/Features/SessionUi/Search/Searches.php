<?php

namespace Tey\Kit\Features\SessionUi\Search;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tey\Kit\Features\Access\Access;
use Tey\Kit\Features\Core\Principal;
use Tey\Kit\Features\Core\Registry;
use Throwable;

/**
 * Runs a global search for one viewer in the current workspace: each registered provider in order, only those whose ability the viewer holds (checked by
 * `Access`, so capability, entitlement and permission all apply), each on its own. A provider that throws, or answers with anything but SearchResults,
 * is skipped and reported by key; the others still answer. Call it inside the workspace's tenant context, after authorizing `search.read`.
 */
final class Searches
{
    public const ABILITY = 'search.read';

    public const MAX_TERM = 100;

    /** The refusal for a person over their searches a minute: a 429 on the web, a refusal code over MCP. */
    public const RATE_LIMITED = 'rate_limited';

    /**
     * Counts one search for the person, across the page, the palette and the MCP tool, and refuses (`rate_limited`) past `kit.search.per_minute`
     * (30 by default). Call it before running a search.
     */
    public static function throttle(Principal $principal): void
    {
        $key = 'kit-search:'.$principal->actorId;
        if (RateLimiter::tooManyAttempts($key, max(1, (int) config('kit.search.per_minute', 30)))) {
            throw new \RuntimeException(self::RATE_LIMITED);
        }
        RateLimiter::hit($key, 60);
    }

    /**
     * @return array{groups: list<array{key: string, label: string, results: list<SearchResult>}>, failed: list<string>} groups with results only, in
     *                                                                                                                    order; the keys of providers that failed
     */
    public function run(Principal $principal, int $workspace, string $term, int $limit, ?Request $request = null): array
    {
        $term = mb_substr(trim($term), 0, self::MAX_TERM);
        if ($term === '') {
            return ['groups' => [], 'failed' => []];
        }
        $query = new SearchQuery($term, $principal, $workspace, max(1, $limit), $request);
        $groups = [];
        $failed = [];
        foreach (app(Registry::class)->searchProviders() as $entry) {
            if (! $this->allowed($principal, $entry['ability'])) {
                continue;
            }
            try {
                $results = (new $entry['provider'])->search($query);
                if (! array_is_list($results) || array_filter($results, fn ($result) => ! $result instanceof SearchResult) !== []) {
                    throw new \UnexpectedValueException('search_provider_results');
                }
                if ($results !== []) {
                    $groups[] = ['key' => $entry['key'], 'label' => $entry['label'], 'results' => array_slice($results, 0, $query->limit)];
                }
            } catch (Throwable $exception) {
                $this->report($entry['key'], $exception);
                $failed[] = $entry['key'];
            }
        }

        return ['groups' => $groups, 'failed' => $failed];
    }

    /** Fail closed: an ability no feature declares, or any refusal, leaves the provider out. */
    private function allowed(Principal $principal, string $ability): bool
    {
        try {
            app(Access::class)->authorize($principal, $ability);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** A failure's class and its trace frames only: never the message, the term, arguments, bindings or paths. */
    private function report(string $key, Throwable $exception): void
    {
        $trace = array_map(fn (array $frame): array => array_intersect_key($frame, array_flip(['class', 'function', 'type', 'line'])), $exception->getTrace());
        Log::warning('Search provider failed', ['provider' => $key, 'exception_class' => $exception::class, 'trace' => $trace]);
    }
}
