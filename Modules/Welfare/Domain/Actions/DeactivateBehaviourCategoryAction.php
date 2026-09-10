<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Welfare\Models\BehaviourCategory;

/**
 * ACT-DeactivateBehaviourCategory (Book G BRD-07 §3 — "the seeded set
 * cannot be emptied: at least one trigger category must remain
 * active"). Enforced here at the Action layer since a DB constraint
 * cannot express "at least one row in this school matching a
 * condition".
 */
final class DeactivateBehaviourCategoryAction extends Action
{
    public function execute(int $categoryId): BehaviourCategory
    {
        $category = BehaviourCategory::findOrFail($categoryId);

        if ($category->is_safeguarding_trigger) {
            $remainingActiveTriggers = BehaviourCategory::query()
                ->where('school_id', $category->school_id)
                ->where('is_safeguarding_trigger', true)
                ->where('is_active', true)
                ->where('id', '!=', $category->id)
                ->exists();

            if (! $remainingActiveTriggers) {
                throw new InvalidStateTransitionException(
                    'At least one active safeguarding-trigger category must remain — refusing to deactivate the last one.',
                    ['category_id' => $category->id, 'school_id' => $category->school_id],
                );
            }
        }

        return $this->transaction(fn (): BehaviourCategory => tap($category)->update(['is_active' => false]));
    }
}
