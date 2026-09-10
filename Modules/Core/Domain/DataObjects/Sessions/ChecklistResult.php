<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

final readonly class ChecklistResult
{
    /**
     * @param  array<int, ChecklistItemResult>  $items
     */
    public function __construct(
        public array $items,
    ) {}

    public function passesBlocking(): bool
    {
        foreach ($this->items as $item) {
            if ($item->blocking && ! $item->passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, ChecklistItemResult>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->items, fn (ChecklistItemResult $item): bool => ! $item->passed));
    }
}
