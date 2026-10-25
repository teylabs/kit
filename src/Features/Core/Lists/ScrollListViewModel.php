<?php

namespace Tey\Kit\Features\Core\Lists;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\ScrollMetadata;
use Inertia\ScrollProp;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\ViewModels\ViewModel;
use Throwable;

/**
 * A cursor-paged list that loads more as the viewer scrolls (Inertia's `Inertia::scroll`). This is the cursor-only, fixed-selection, count-free subset:
 * there are no sorts, segments, totals, polling, page or simple modes. Cursor order is deterministic but not a snapshot: edited or moved records can
 * move between windows.
 *
 * The continuation is encrypted and carries the viewer, workspace, list key, selection and mode together with the position, so another viewer,
 * workspace, list or selection cannot replay it. A bad or foreign continuation is a 400 and never becomes a first-page request. `query()` must be
 * ordered (cursor pagination needs a unique, deterministic order) and already scoped by policy and tenant: nothing here infers access.
 */
abstract class ScrollListViewModel extends ViewModel
{
    protected array $hidden = ['items'];

    public function __construct(protected Request $request, protected int|string $workspace) {}

    /** Unique per page: it names the prop and its continuation parameter (`<key>_cursor`). */
    abstract public function key(): string;

    abstract public function title(): string;

    /** The workspace the request is in now, or null when there is none. The list is a 404 unless it equals the one it was built for. */
    abstract protected function activeWorkspace(): int|string|null;

    abstract protected function query(): Builder;

    abstract protected function row(Model $model): ListItemData;

    /** What an empty first window says (first use). */
    abstract protected function empty(): ListEmptyData;

    protected function perPage(): int
    {
        return 20;
    }

    /** The deferred group the first window joins. */
    protected function group(): string
    {
        return 'lists';
    }

    /** Called with a failure's class and its trace frames only: never the message, arguments, bindings or paths. */
    protected function report(Throwable $exception): void
    {
        $trace = array_map(fn (array $frame): array => array_intersect_key($frame, array_flip(['class', 'function', 'type', 'line'])), $exception->getTrace());
        Log::warning('List failed', ['list' => $this->key(), 'exception_class' => $exception::class, 'trace' => $trace]);
    }

    final public function items(): ScrollProp
    {
        $metadata = new ScrollMetadata($this->key().'_cursor');

        return Inertia::scroll(function () use (&$metadata): array {
            if ($this->activeWorkspace() === null || (string) $this->activeWorkspace() !== (string) $this->workspace || $this->request->user() === null) {
                throw new HttpException(404);
            }
            $cursor = null;
            $encoded = $this->request->query($this->key().'_cursor');
            if ($encoded !== null) {
                $cursor = $this->decode($encoded);
            }
            // These lists declare no selectable sort or segment. Unknown selections are rejected.
            if ($this->request->has($this->key())) {
                throw new HttpException(400);
            }
            try {
                $page = $this->query()->cursorPaginate($this->perPage(), cursorName: $this->key().'_cursor', cursor: $cursor);
                $rows = $page->getCollection()->map(fn (Model $model) => $this->row($model))->all();
                $next = $page->nextCursor();
                $metadata = new ScrollMetadata($this->key().'_cursor', null, $next === null ? null : $this->encode($next), $encoded ?? 1);

                // No filters are exposed, so an empty first window is first use; an empty later one means the list changed.
                $empty = null;
                if ($rows === []) {
                    $empty = $encoded !== null && $this->query()->exists()
                        ? new ListEmptyData('no-results', 'No more results', 'The list changed while you were viewing it.', new Action('Start again', $this->request->getPathInfo(), 'get', key: $this->key()))
                        : $this->empty();
                }

                return (new ScrollListData($this->key(), $this->title(), $rows === [] ? 'empty' : 'ready', $rows, $empty))->toArray();
            } catch (Throwable $exception) {
                $this->report($exception);
                $metadata = new ScrollMetadata($this->key().'_cursor', null, $encoded, $encoded ?? 1);

                return (new ScrollListData($this->key(), $this->title(), 'error', [], error: new ListErrorData("This couldn't load.", new Action('Retry', $this->request->getPathInfo(), 'get', key: $this->key()))))->toArray();
            }
        }, metadata: function () use (&$metadata) {
            // By reference: the loader above replaces it once it knows the next position.
            return $metadata;
        })->defer($this->group());
    }

    private function encode(Cursor $position): string
    {
        return Crypt::encryptString(json_encode(['scope' => $this->scope(), 'position' => $position->encode()], JSON_THROW_ON_ERROR));
    }

    private function decode(mixed $encoded): Cursor
    {
        try {
            $payload = json_decode(Crypt::decryptString((string) $encoded), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ($payload['scope'] ?? null) !== $this->scope() || ! is_string($payload['position'] ?? null)) {
                throw new HttpException(400);
            }
            $cursor = Cursor::fromEncoded($payload['position']);
        } catch (Throwable) {
            throw new HttpException(400);
        }

        return $cursor ?? throw new HttpException(400);
    }

    /** @return array{viewer: int|string, workspace: int|string, list: string, selection: string, mode: string} */
    private function scope(): array
    {
        return ['viewer' => $this->request->user()->getKey(), 'workspace' => $this->workspace, 'list' => $this->key(), 'selection' => 'fixed-v1', 'mode' => 'cursor'];
    }
}
