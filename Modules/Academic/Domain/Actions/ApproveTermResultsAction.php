<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\ApproveTermResultsData;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ApproveTermResults (Book D ACA-05 §3/§6, BR-ACA-05-017/020). The
 * deliberate review step between computing results and generating report
 * cards: `computed` (or `reviewed`) results become `approved`. A result that
 * is already approved, published or withheld is left alone, and a result with
 * no subject results at all cannot be approved.
 */
final class ApproveTermResultsAction extends Action
{
    /**
     * @return int how many results were approved
     */
    public function execute(ApproveTermResultsData $data): int
    {
        $results = TermResult::query()
            ->where('term_id', $data->termId)
            ->when($data->classId !== null, fn ($q) => $q->where('class_id', $data->classId))
            ->whereIn('status', ['computed', 'reviewed'])
            ->get();

        if ($results->isEmpty()) {
            throw new InvalidArgumentException('There are no computed results to approve for that selection.');
        }

        return $this->transaction(function () use ($results, $data): int {
            $approved = 0;

            foreach ($results as $result) {
                if ($result->subjects_taken < 1) {
                    continue;
                }

                $result->update(['status' => 'approved', 'approved_by' => $data->approvedByUserId]);
                $approved++;
            }

            return $approved;
        });
    }
}
