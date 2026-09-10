<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ScriptBatch;
use Modules\Core\Models\School;

/**
 * @extends Factory<ScriptBatch>
 */
class ScriptBatchFactory extends Factory
{
    protected $model = ScriptBatch::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'paper_id' => ExaminationPaper::factory()->for($school),
            'batch_reference' => 'BATCH-'.fake()->unique()->numberBetween(1, 99999),
            'script_count' => 30,
            'expected_count' => 30,
            'status' => 'collected',
        ];
    }
}
