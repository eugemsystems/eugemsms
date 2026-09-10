<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\ReturnIssuedItemData;
use Modules\Boarding\Models\IssuableItem;
use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\Term;

/**
 * ACT-ReturnIssuedItem (Book F BRD-05 §3/BR-BRD-05-003/AC-BRD-05-002).
 * Condition degrading beyond fair wear — i.e. returned in poor
 * condition — raises a damage charge (status becomes `damaged`,
 * pending `ApproveIssuedItemDamageChargeAction`), but only while the
 * item is still within its catalogue's `expected_lifespan_terms`.
 * Normal wear once that lifespan has elapsed is never charged — the
 * item simply returns.
 */
final class ReturnIssuedItemAction extends Action
{
    private const CONDITION_RANK = ['new' => 0, 'good' => 1, 'fair' => 2, 'poor' => 3];

    public function execute(ReturnIssuedItemData $data): LearnerIssuedItem
    {
        $issuedItem = LearnerIssuedItem::findOrFail($data->learnerIssuedItemId);

        if ($issuedItem->status !== 'issued') {
            throw new InvalidStateTransitionException(
                "Item #{$issuedItem->id} must be issued to be returned (currently {$issuedItem->status}).",
                ['learner_issued_item_id' => $issuedItem->id, 'status' => $issuedItem->status],
            );
        }

        $chargeable = $this->isChargeableDamage($issuedItem, $data->conditionAtReturn);

        return $this->transaction(fn (): LearnerIssuedItem => tap($issuedItem)->update([
            'returned_on' => $data->returnedOn->toDateString(),
            'condition_at_return' => $data->conditionAtReturn,
            'received_by' => $data->receivedByUserId,
            'status' => $chargeable ? 'damaged' : 'returned',
            'notes' => $data->notes ?? $issuedItem->notes,
        ]));
    }

    private function isChargeableDamage(LearnerIssuedItem $issuedItem, string $conditionAtReturn): bool
    {
        $degradedBeyondFair = (self::CONDITION_RANK[$conditionAtReturn] ?? 0) > self::CONDITION_RANK['fair'];

        if (! $degradedBeyondFair) {
            return false;
        }

        $item = IssuableItem::findOrFail($issuedItem->issuable_item_id);

        if ($item->expected_lifespan_terms === null) {
            return true;
        }

        $issuedTerm = Term::findOrFail($issuedItem->term_id);
        $currentTerm = Term::query()->where('school_id', $issuedItem->school_id)->where('is_current', true)->first() ?? $issuedTerm;

        $elapsedTerms = Term::query()
            ->where('school_id', $issuedItem->school_id)
            ->whereDate('starts_on', '>=', $issuedTerm->starts_on->toDateString())
            ->whereDate('starts_on', '<=', $currentTerm->starts_on->toDateString())
            ->count();

        return $elapsedTerms <= $item->expected_lifespan_terms;
    }
}
