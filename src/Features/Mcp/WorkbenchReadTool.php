<?php

namespace Tey\Kit\Features\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;
use Tey\Kit\Features\Core\Data\WorkbenchItem;
use Tey\Kit\Features\Core\Principal;

/**
 * An optional read tool for a Workbench (a deck and its slides, a valuation and its comparables): the assistant reads what the person sees in the navigator, the
 * current item and whatever the app adds, through the same principal-first operation as the page. Read only. A subclass names its capability and ability as any
 * CapabilityTool does, the argument that identifies the record (`subject()`, `id` by default) and `read()`, which returns at least `items` (WorkbenchItem or
 * arrays of the same shape). Anything else it returns is passed through. A record the operation cannot find is refused with `notFoundCode()`.
 */
abstract class WorkbenchReadTool extends CapabilityTool
{
    /** Read only, for every subclass: attributes are not inherited, so the hint is added here rather than declared on this class. */
    public function annotations(): array
    {
        $readOnly = new IsReadOnly;

        return [...parent::annotations(), $readOnly->key() => $readOnly->value];
    }

    /**
     * @return array{items: list<WorkbenchItem|array{id: string, label: string, type?: ?string, description?: ?string}>, current?: ?string}&array<string, mixed>
     */
    abstract protected function read(Principal $principal, int $workspace, int $subject): array;

    /** The argument naming the record (`deck_id`). */
    protected function subject(): string
    {
        return 'id';
    }

    public function schema(JsonSchema $schema): array
    {
        return [$this->subject() => $schema->integer()->min(1)->required()];
    }

    protected function rules(): array
    {
        return [$this->subject() => ['required', 'integer', 'min:1']];
    }

    protected function call(Principal $principal, int $workspace, array $validated): array
    {
        $read = $this->read($principal, $workspace, (int) $validated[$this->subject()]);
        if (! isset($read['items']) || ! is_array($read['items']) || ! array_is_list($read['items'])) {
            throw new RuntimeException('A workbench read returns its navigator items as a list.');
        }
        $read['items'] = array_map(static function (mixed $item): array {
            $item = $item instanceof WorkbenchItem ? $item->toArray() : $item;
            if (! is_array($item) || ! is_string($item['id'] ?? null) || ! is_string($item['label'] ?? null)) {
                throw new RuntimeException('A workbench item has a string id and label.');
            }

            return ['id' => $item['id'], 'label' => $item['label'], 'type' => $item['type'] ?? null, 'description' => $item['description'] ?? null];
        }, $read['items']);
        $read['current'] ??= null;

        return $read;
    }
}
