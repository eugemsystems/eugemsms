<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Boarding\Domain\DataObjects\IssueItemToLearnerData;
use Modules\Boarding\Models\IssuableItem;
use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-IssueItemToLearner (Book F BRD-05 §3/BR-BRD-05-001). Records the
 * condition at issue and, when the item's catalogue entry requires
 * tagging, refuses to proceed without a tag reference.
 */
final class IssueItemToLearnerAction extends Action
{
    public function execute(IssueItemToLearnerData $data): LearnerIssuedItem
    {
        $item = IssuableItem::findOrFail($data->issuableItemId);

        if ($item->requires_tagging && $data->tagReference === null) {
            throw ValidationException::withMessages([
                'tagReference' => "Issuing {$item->name} requires a tag reference.",
            ]);
        }

        return $this->transaction(fn (): LearnerIssuedItem => LearnerIssuedItem::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'student_id' => $data->studentId,
            'issuable_item_id' => $data->issuableItemId,
            'tag_reference' => $data->tagReference,
            'quantity' => $data->quantity,
            'condition_at_issue' => $data->conditionAtIssue,
            'issued_on' => $data->issuedOn->toDateString(),
            'issued_by' => $data->issuedByUserId,
            'status' => 'issued',
            'notes' => $data->notes,
        ]));
    }
}
