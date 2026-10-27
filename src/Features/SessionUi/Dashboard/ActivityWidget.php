<?php

namespace Tey\Kit\Features\SessionUi\Dashboard;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Tey\Kit\Features\Core\Users;

/**
 * The workspace's recent activity: its latest audit events, newest first, as who did what and when. An event's details are never shown (they can name
 * records the viewer may not open), only its name, so the widget is registered behind `members.manage`.
 */
#[TypeScript]
final class ActivityWidget extends Widget
{
    public const LIMIT = 10;

    /** @var list<array{id: string, actor: string, event: string, at: string}>|null */
    private ?array $items = null;

    public function title(): string
    {
        return 'Recent activity';
    }

    public function component(): string
    {
        return 'activity';
    }

    public function span(): WidgetSpanData
    {
        return new WidgetSpanData(md: 6, lg: 8);
    }

    public function state(): string
    {
        return $this->items() === [] ? 'empty' : 'ready';
    }

    public function empty(): ?WidgetEmptyData
    {
        return $this->state() === 'empty' ? new WidgetEmptyData('No activity yet', 'What people do in this workspace will show up here.') : null;
    }

    /** @return list<array{id: string, actor: string, event: string, at: string}> */
    public function items(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }
        $events = DB::table('audit_events')->where('workspace_id', $this->context->workspace)->orderByDesc('created_at')->orderByDesc('id')->limit(self::LIMIT)->get(['id', 'actor_id', 'event', 'created_at']);
        $model = Users::model();
        $names = $model::query()->whereKey($events->pluck('actor_id')->unique()->all())->pluck('name', (new $model)->getKeyName());

        return $this->items = $events->map(fn (object $event): array => [
            'id' => (string) $event->id,
            'actor' => (string) ($names[$event->actor_id] ?? 'Someone'),
            'event' => Str::ucfirst(str_replace(['.', '_', '-'], ' ', (string) $event->event)),
            'at' => Carbon::parse($event->created_at)->toIso8601String(),
        ])->values()->all();
    }
}
