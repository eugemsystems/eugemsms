<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Academic\Domain\DataObjects\VetExaminationPaperData;
use Modules\Academic\Domain\Exceptions\SetterCannotVetOwnPaperException;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-VetExaminationPaper (Book E ACA-07 §3 ⭐/AC-ACA-07-003). Setter
 * separation is enforced here, unconditionally: the paper's own
 * `setter_staff_id` can never also be `vetted_by`.
 */
final class VetExaminationPaperAction extends Action
{
    public function execute(VetExaminationPaperData $data): ExaminationPaper
    {
        $paper = ExaminationPaper::findOrFail($data->paperId);

        if (in_array($paper->status, ['sealed', 'released', 'sat'], true)) {
            throw new InvalidStateTransitionException(
                "Paper #{$paper->id} is already {$paper->status} and cannot be re-vetted.",
                ['paper_id' => $paper->id, 'status' => $paper->status],
            );
        }

        if ($paper->setter_staff_id !== null && $paper->setter_staff_id === $data->vettedByStaffId) {
            throw SetterCannotVetOwnPaperException::forPaper($paper->id);
        }

        return $this->transaction(function () use ($paper, $data): ExaminationPaper {
            $paper->update([
                'vetted_by' => $data->vettedByStaffId,
                'vetted_at' => Carbon::now(),
                'status' => 'vetted',
            ]);

            return $paper;
        });
    }
}
