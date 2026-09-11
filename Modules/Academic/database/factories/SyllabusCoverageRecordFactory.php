<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\SchemeOfWork;
use Modules\Academic\Models\SyllabusCoverageRecord;

/**
 * @extends Factory<SyllabusCoverageRecord>
 */
class SyllabusCoverageRecordFactory extends Factory
{
    protected $model = SyllabusCoverageRecord::class;

    public function definition(): array
    {
        $scheme = SchemeOfWork::factory()->create();

        return [
            'school_id' => $scheme->school_id,
            'scheme_of_work_id' => $scheme->id,
            'planned_topic_index' => 0,
        ];
    }
}
