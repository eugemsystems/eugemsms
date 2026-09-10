<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\RegisterProcessingActivityData;
use Modules\Compliance\Models\ProcessingRegisterEntry;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RegisterProcessingActivity (Book H3 CMP-03 §3/BR-CMP-03-013).
 * Each module registers its own entries — `owningModule` is required
 * so the register itself shows where an activity comes from.
 */
final class RegisterProcessingActivityAction extends Action
{
    public function execute(RegisterProcessingActivityData $data): ProcessingRegisterEntry
    {
        return $this->transaction(fn (): ProcessingRegisterEntry => ProcessingRegisterEntry::create([
            'school_id' => $data->schoolId,
            'activity_name' => $data->activityName,
            'purpose' => $data->purpose,
            'lawful_basis' => $data->lawfulBasis,
            'data_categories' => $data->dataCategories,
            'subject_categories' => $data->subjectCategories,
            'recipients' => $data->recipients,
            'retention_schedule_id' => $data->retentionScheduleId,
            'involves_minors' => $data->involvesMinors,
            'is_special_category' => $data->isSpecialCategory,
            'security_measures' => $data->securityMeasures,
            'owning_module' => $data->owningModule,
            'last_reviewed_on' => Carbon::now()->toDateString(),
        ]));
    }
}
