<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\CreateZimsecValidationRuleData;
use Modules\Compliance\Models\ZimsecValidationRule;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateZimsecValidationRule (Book H3 CMP-01 §2/BR-CMP-01-003).
 * ZIMSEC's own field requirements change between series — this is how
 * a school (or a system default, when `schoolId` is null) adjusts
 * validation without a code change.
 */
final class CreateZimsecValidationRuleAction extends Action
{
    public function execute(CreateZimsecValidationRuleData $data): ZimsecValidationRule
    {
        return $this->transaction(fn (): ZimsecValidationRule => ZimsecValidationRule::create([
            'school_id' => $data->schoolId,
            'exam_level' => $data->examLevel,
            'field' => $data->field,
            'rule_type' => $data->ruleType,
            'rule_value' => $data->ruleValue,
            'severity' => $data->severity,
            'message' => $data->message,
            'is_active' => true,
        ]));
    }
}
