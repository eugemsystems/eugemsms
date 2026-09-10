<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Core\Models\TermWeek;

/**
 * @extends Factory<TermWeek>
 */
class TermWeekFactory extends Factory
{
    protected $model = TermWeek::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => Term::factory(),
            'week_number' => 1,
            'starts_on' => now()->startOfWeek(),
            'ends_on' => now()->startOfWeek()->addDays(6),
            'is_teaching_week' => true,
        ];
    }
}
