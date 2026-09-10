<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Sessions;

use Modules\Core\Domain\Actions\Sessions\TakePeriodSnapshotAction;
use Modules\Core\Domain\Contracts\Sessions\RolloverHandler;
use Modules\Core\Domain\DataObjects\Sessions\HandlerResult;
use Modules\Core\Domain\DataObjects\Sessions\RolloverContext;
use Modules\Core\Domain\DataObjects\Sessions\SnapshotData;
use Modules\Core\Domain\DataObjects\Sessions\ValidationResult;
use Modules\Core\Models\Term;

/**
 * Book A CORE-03 §5, order 60 — the forensic anchor taken immediately
 * before any roll-over handler writes anything.
 */
final class TakePreCloseSnapshotHandler implements RolloverHandler
{
    public function __construct(
        private readonly TakePeriodSnapshotAction $takeSnapshot,
    ) {}

    public function moduleCode(): string
    {
        return 'CORE-03';
    }

    public function sortOrder(): int
    {
        return 60;
    }

    public function isBlocking(): bool
    {
        return true;
    }

    public function validate(Term $from, Term $to): ValidationResult
    {
        return ValidationResult::pass();
    }

    public function execute(Term $from, Term $to, RolloverContext $context): HandlerResult
    {
        $snapshot = $this->takeSnapshot->execute(new SnapshotData(
            schoolId: $from->school_id,
            academicYearId: $from->academic_year_id,
            termId: $from->id,
            snapshotType: 'pre_close',
            takenByUserId: $context->rollover->initiated_by,
        ));

        $context->put('pre_close_snapshot_id', $snapshot->id);

        return new HandlerResult(true, 'Pre-close snapshot taken.', ['snapshot_id' => $snapshot->id]);
    }

    public function rollback(Term $from, Term $to, RolloverContext $context): void
    {
        // The snapshot itself is append-only (BR-CORE-03-016) and is
        // never deleted, even on rollback — it's a true record of what
        // was attempted, not of what succeeded.
    }
}
