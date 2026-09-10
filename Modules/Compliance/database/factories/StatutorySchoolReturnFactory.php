<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\StatutorySchoolReturn;
use Modules\Core\Models\School;

/**
 * @extends Factory<StatutorySchoolReturn>
 */
class StatutorySchoolReturnFactory extends Factory
{
    protected $model = StatutorySchoolReturn::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'return_type' => 'term_enrolment',
            'period_reference' => 'Term '.now()->format('Y').'-1',
            'due_date' => now()->addWeeks(2)->toDateString(),
            'authority' => 'MoPSE District',
            'data_snapshot' => ['generated_at' => now()->toIso8601String(), 'counts' => []],
            'validation_result' => null,
            'quality_issues' => 0,
            'export_file_id' => null,
            'status' => 'pending',
            'generated_by' => null,
            'submitted_at' => null,
            'submitted_by' => null,
            'acknowledgement_ref' => null,
        ];
    }
}
