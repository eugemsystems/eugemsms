<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\RetentionSchedule;
use Modules\Core\Models\School;

/**
 * @extends Factory<RetentionSchedule>
 */
class RetentionScheduleFactory extends Factory
{
    protected $model = RetentionSchedule::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'record_class' => 'academic_record',
            'table_names' => ['students'],
            'retention_years' => '7.00',
            'retention_trigger' => 'learner_exit',
            'disposal_method' => 'anonymise',
            'legal_basis' => 'Cyber and Data Protection Act [Chapter 12:07]',
            'requires_review' => true,
            'is_active' => true,
        ];
    }
}
