<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Illuminate\Support\Collection;
use Modules\Academic\Models\PeriodSlot;
use Modules\Academic\Models\TimetableSlot;

/**
 * Book E ACA-03 §3/§7 ⭐. What `MoveTimetableSlotAction::execute()` would
 * do for a given (slot, day, period) without writing anything —
 * `blockedReason` for a lock/double/published/not-teachable refusal,
 * or `clashes` for the four clash levels, exactly as `execute()` itself
 * would throw. Powers the editor's live drag-over clash panel
 * (`Editor::previewMove()`), called once per cell the dragged lesson
 * enters, as well as `execute()`'s own pre-write check — one source of
 * truth for both, never duplicated logic.
 */
final readonly class TimetableSlotMovePreview
{
    /**
     * @param  Collection<int, TimetableClash>  $clashes
     */
    public function __construct(
        public TimetableSlot $slot,
        public ?PeriodSlot $target,
        public ?string $blockedReason,
        public Collection $clashes,
    ) {}

    public function isClear(): bool
    {
        return $this->blockedReason === null && $this->clashes->isEmpty();
    }
}
