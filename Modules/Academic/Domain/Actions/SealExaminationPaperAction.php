<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\SealExaminationPaperData;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-SealExaminationPaper (Book E ACA-07 §3 ⭐, "sealed status is
 * one-way"). A paper cannot reach `sealed` without a recorded vetting
 * by a different staff member (BR-ACA-07 §3), and once sealed it can
 * never return to `draft` — a correction is a new paper version with
 * an audit note, not a re-opened seal (deferred: paper versioning
 * itself, no screen/action needs it yet in this pass).
 */
final class SealExaminationPaperAction extends Action
{
    public function execute(SealExaminationPaperData $data): ExaminationPaper
    {
        $paper = ExaminationPaper::findOrFail($data->paperId);

        if ($paper->status !== 'vetted') {
            throw new InvalidStateTransitionException(
                "Paper #{$paper->id} must be vetted before it can be sealed (currently {$paper->status}).",
                ['paper_id' => $paper->id, 'status' => $paper->status],
            );
        }

        return $this->transaction(function () use ($paper, $data): ExaminationPaper {
            $paper->update([
                'release_at' => $data->releaseAt,
                'status' => 'sealed',
            ]);

            return $paper;
        });
    }
}
