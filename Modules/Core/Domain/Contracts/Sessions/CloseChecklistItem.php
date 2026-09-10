<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Sessions;

use Modules\Core\Domain\DataObjects\Sessions\ChecklistItemResult;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\Term;

/**
 * Book A CORE-03 §6, BR-CORE-03-014 — one item on the financial (or
 * academic) close checklist. Every module that owns period-bound data
 * that must be sound before a term can `LOCK` registers one of these
 * into `CloseChecklistRegistry`, the same "code owns the list" split as
 * `RolloverHandlerRegistry`.
 */
interface CloseChecklistItem
{
    public function code(): string;

    public function label(): string;

    public function appliesTo(): PeriodType;

    public function isBlocking(): bool;

    public function check(Term $term): ChecklistItemResult;
}
