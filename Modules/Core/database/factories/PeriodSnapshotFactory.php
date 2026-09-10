<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\PeriodSnapshot;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<PeriodSnapshot>
 */
class PeriodSnapshotFactory extends Factory
{
    protected $model = PeriodSnapshot::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'term_id' => Term::factory(),
            'snapshot_type' => 'pre_close',
            'taken_at' => now(),
            'payload' => ['example' => true],
            'payload_hash' => hash('sha256', 'example'),
            'previous_hash' => null,
            'row_counts' => ['example' => 1],
        ];
    }
}
