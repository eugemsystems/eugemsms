<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Intelligence\Models\WithdrawalRiskFlag;

/**
 * ACT-ReviewWithdrawalRiskFlag (Book J INT-03 §2/BR-INT-03-008
 * (AC-INT-03-004)). Every non-open status is a closure — it always
 * requires an `intervention_note`, so a flag either produces a
 * documented action or a documented decision not to act, never a
 * silent dismissal.
 */
final class ReviewWithdrawalRiskFlagAction extends Action
{
    private const array CLOSING_STATUSES = ['intervention_logged', 'resolved', 'withdrawn'];

    public function execute(int $flagId, string $status, int $reviewerUserId, ?string $interventionNote = null): WithdrawalRiskFlag
    {
        if (in_array($status, self::CLOSING_STATUSES, true) && ($interventionNote === null || trim($interventionNote) === '')) {
            throw new InvalidArgumentException('Closing a withdrawal risk flag requires an intervention note (BR-INT-03-008).');
        }

        $flag = WithdrawalRiskFlag::findOrFail($flagId);

        return $this->transaction(function () use ($flag, $status, $reviewerUserId, $interventionNote): WithdrawalRiskFlag {
            $flag->update([
                'status' => $status, 'reviewed_by' => $reviewerUserId, 'intervention_note' => $interventionNote,
            ]);

            return $flag->fresh();
        });
    }
}
