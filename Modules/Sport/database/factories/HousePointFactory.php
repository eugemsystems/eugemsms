<?php

declare(strict_types=1);

namespace Modules\Sport\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\House;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Sport\Models\HousePoint;

/**
 * @extends Factory<HousePoint>
 */
class HousePointFactory extends Factory
{
    protected $model = HousePoint::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'term_id' => fn (array $attributes): int => Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $attributes['academic_year_id']])->id,
            'house_id' => fn (array $attributes): int => House::factory()->create(['school_id' => $attributes['school_id']])->id,
            'source_type' => 'manual',
            'points' => 10,
            'awarded_at' => now(),
            'awarded_by' => User::factory(),
        ];
    }
}
