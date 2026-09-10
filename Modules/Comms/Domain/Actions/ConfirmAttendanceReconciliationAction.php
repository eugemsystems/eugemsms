<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Academic\Domain\Actions\MarkAttendanceAction;
use Modules\Academic\Domain\DataObjects\MarkAttendanceData;
use Modules\Academic\Domain\DataObjects\MarkAttendanceRecordInput;
use Modules\Academic\Domain\DataObjects\MarkAttendanceResult;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ConfirmAttendanceReconciliation (Book I COM-07 §3 ⭐/BR-COM-07-007
 * (AC-COM-07-002)). The ONE path that ever writes a real `ACA-04`
 * attendance record from a video-conference signal — always through
 * the real, unmodified `MarkAttendanceAction`, with the teacher's own
 * chosen `status` (never inferred by this action), so nothing here
 * "auto-locks a register" or bypasses ACA-04's own idempotency/
 * conflict handling.
 */
final class ConfirmAttendanceReconciliationAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly MarkAttendanceAction $markAttendance,
    ) {}

    /**
     * @param  array<int, array{studentId: int, status: string, note?: string}>  $decisions
     */
    public function execute(int $sessionId, array $decisions, int $markedByUserId): MarkAttendanceResult
    {
        $records = array_map(
            fn (array $decision): MarkAttendanceRecordInput => new MarkAttendanceRecordInput(
                studentId: $decision['studentId'],
                status: $decision['status'],
                note: $decision['note'] ?? null,
            ),
            $decisions,
        );

        return $this->markAttendance->execute(new MarkAttendanceData(
            sessionId: $sessionId,
            records: $records,
            markedByUserId: $markedByUserId,
        ));
    }
}
