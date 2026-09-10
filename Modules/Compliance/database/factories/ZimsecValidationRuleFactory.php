<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\ZimsecValidationRule;

/**
 * @extends Factory<ZimsecValidationRule>
 */
class ZimsecValidationRuleFactory extends Factory
{
    protected $model = ZimsecValidationRule::class;

    public function definition(): array
    {
        return [
            'school_id' => null,
            'exam_level' => null,
            'field' => 'national_registration_no',
            'rule_type' => 'required',
            'rule_value' => null,
            'severity' => 'error',
            'message' => 'National registration number is required.',
            'is_active' => true,
        ];
    }
}
