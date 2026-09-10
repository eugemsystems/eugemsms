<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\CreateBehaviourCategoryData;
use Modules\Welfare\Models\BehaviourCategory;

/**
 * ACT-CreateBehaviourCategory (Book G BRD-07 §2).
 */
final class CreateBehaviourCategoryAction extends Action
{
    public function execute(CreateBehaviourCategoryData $data): BehaviourCategory
    {
        return $this->transaction(fn (): BehaviourCategory => BehaviourCategory::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'polarity' => $data->polarity,
            'default_points' => $data->defaultPoints,
            'severity_level' => $data->severityLevel,
            'requires_evidence' => $data->requiresEvidence,
            'requires_head_review' => $data->requiresHeadReview,
            'auto_notify_guardian' => $data->autoNotifyGuardian,
            'suggests_sanction_id' => $data->suggestsSanctionId,
            'is_safeguarding_trigger' => $data->isSafeguardingTrigger,
            'sort_order' => $data->sortOrder,
            'is_active' => true,
        ]));
    }
}
