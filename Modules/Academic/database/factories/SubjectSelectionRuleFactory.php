<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\SubjectSelectionRule;
use Modules\Core\Models\School;

/**
 * @extends Factory<SubjectSelectionRule>
 */
class SubjectSelectionRuleFactory extends Factory
{
    protected $model = SubjectSelectionRule::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'framework_id' => CurriculumFramework::factory()->for($school),
            'rule_type' => 'max_total',
            'max_count' => 8,
            'severity' => 'block',
            'message' => 'A maximum of 8 subjects may be selected.',
            'requires_confirmation' => false,
            'is_active' => true,
        ];
    }
}
