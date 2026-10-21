<?php

namespace Tey\Kit\Features\SessionUi;

use Tey\Concierge\Data\UI\InvitationRow;
use Tey\Concierge\Data\UI\MemberRow;
use Tey\Concierge\Data\UI\MembersTable;
use Tey\Concierge\Data\UI\RowAction;
use Tey\Kit\Features\Core\Data\Action;
use Tey\Kit\Features\Core\Data\TableColumn;
use Tey\Kit\Features\Core\Data\TableData;
use Tey\Kit\Features\Core\Data\TablePagination;
use Tey\Kit\Features\Core\Data\TableSort;
use Tey\Kit\Features\Core\Data\TableTab;

/**
 * Concierge's members table (rows, columns, sort, search, filters, tabs, pagination, empty state and the actions each row's viewer may take, decided by its rank
 * rules) shaped as the kit's generic TableData. It only renames and reshapes: no membership logic, no policy call, no query.
 */
final class PeopleTable
{
    public static function from(MembersTable $table, PageShell $shell): TableData
    {
        // One query for every avatar on the page, never one per row.
        $avatars = Avatars::urlsFor(array_values(array_map(fn ($row) => (int) $row->user->id, array_filter($table->rows, fn ($row) => $row instanceof MemberRow))));

        return new TableData(
            array_map(fn ($row) => $row instanceof MemberRow ? self::member($row, $shell, $avatars[(int) $row->user->id] ?? null) : self::invitation($row), $table->rows),
            // Concierge names the Member column "member" but its allowed sort "name": the descriptor carries the sort it accepts, so the table sends only what the server allows.
            array_map(fn ($column) => new TableColumn($column->key, $column->label, $column->sortable, $column->hideable, $column->hiddenByDefault, $column->align, $column->srOnlyLabel, $column->key === 'member' ? 'name' : null), $table->columns),
            $table->sort === null ? null : new TableSort($table->sort->column, $table->sort->direction),
            $table->search,
            $table->filters,
            array_map(fn ($tab) => new TableTab($tab->key, $tab->label, $tab->url, $tab->count, $tab->current), $table->tabs),
            new TablePagination($table->pagination->page, $table->pagination->perPage, $table->pagination->total, $table->pagination->from, $table->pagination->to, $table->pagination->lastPage),
            $table->emptyState,
        );
    }

    /** @return array<string, mixed> */
    /** @param  array{url: string, url_2x: string}|null  $avatar */
    private static function member(MemberRow $row, PageShell $shell, ?array $avatar): array
    {
        $role = $row->roles[0] ?? null;

        return [
            'id' => $row->id,
            'member' => ['name' => $row->user->name, 'email' => $row->user->email, 'initials' => $shell->initials($row->user->name), 'avatar' => $avatar, 'is_you' => $row->isSelf],
            'roles' => array_map(fn ($badge) => ['value' => $badge->value, 'label' => $badge->label, 'variant' => $badge->variant], $row->roles),
            'role' => $role?->value,
            'status' => $row->status,
            'joined' => $row->joinedAt,
            'actions' => array_map(self::action(...), $row->actions),
        ];
    }

    /** @return array<string, mixed> */
    private static function invitation(InvitationRow $row): array
    {
        $role = $row->roles[0] ?? null;

        return [
            'id' => $row->id,
            'email' => $row->email,
            'roles' => array_map(fn ($badge) => ['value' => $badge->value, 'label' => $badge->label, 'variant' => $badge->variant], $row->roles),
            'role' => $role?->value,
            'status' => $row->status,
            'invitedBy' => $row->invitedBy,
            'sent' => $row->lastSentAt,
            'expires' => $row->expiresAt,
            'actions' => array_map(self::action(...), $row->actions),
        ];
    }

    private static function action(RowAction $action): Action
    {
        return new Action($action->key === 'transfer-ownership' ? 'Make owner' : $action->label, $action->url, $action->method, confirm: $action->confirm === null ? null : ['title' => $action->confirm->title, 'description' => $action->confirm->body, 'label' => $action->confirm->confirmLabel], key: $action->key, destructive: $action->destructive);
    }
}
