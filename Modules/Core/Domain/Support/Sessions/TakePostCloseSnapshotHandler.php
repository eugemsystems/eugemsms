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
 * Book A CORE-03 §5, order 900 — taken once every blocking handler has
 * written its changes, immediately before `VerifyInvariantHandler`.
 */
final class TakePostCloseSnapshotHandler implements RolloverHandler
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
        return 900;
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
            schoolId: $to->school_id,
            academicYearId: $to->academic_year_id,
            termId: $to->id,
            snapshotType: 'post_close',
            takenByUserId: $context->rollover->initiated_by,
        ));

        $context->put('post_close_snapshot_id', $snapshot->id);

        return new HandlerResult(true, 'Post-close snapshot taken.', ['snapshot_id' => $snapshot->id]);
    }

    public function rollback(Term $from, Term $to, RolloverContext $context): void
    {
        // Append-only — see TakePreCloseSnapshotHandler::rollback().
    }
}
