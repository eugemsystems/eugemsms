<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateSubjectSelectionRuleData;
use Modules\Academic\Models\SubjectSelectionRule;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateSubjectSelectionRule (Book D ACA-01 §3 ⭐/BR-ACA-01-007/008/009).
 * New in this admin-UI pass — `SubjectSelectionRuleEngine` (the only
 * code that reads these rows) existed with nothing but
 * `SubjectSelectionRule::factory()` test fixtures writing them; this is
 * the first reviewable write path. Every subject-count limit stays
 * configuration, never a hard-coded constant, per BR-ACA-01-007 — this
 * action is the whole mechanism that keeps that true.
 */
final class CreateSubjectSelectionRuleAction extends Action
{
    public function execute(CreateSubjectSelectionRuleData $data): SubjectSelectionRule
    {
        return $this->transaction(fn (): SubjectSelectionRule => SubjectSelectionRule::create([
            'school_id' => $data->schoolId,
            'framework_id' => $data->frameworkId,
            'grade_level_id' => $data->gradeLevelId,
            'pathway' => $data->pathway,
            'rule_type' => $data->ruleType,
            'subject_group_id' => $data->subjectGroupId,
            'subject_ids' => $data->subjectIds,
            'min_count' => $data->minCount,
            'max_count' => $data->maxCount,
            'severity' => $data->severity,
            'message' => $data->message,
            'source_reference' => $data->sourceReference,
            'requires_confirmation' => $data->requiresConfirmation,
            'is_active' => true,
        ]));
    }
}
