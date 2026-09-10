<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Security\Models\EmergencyDrill;

/**
 * @extends Factory<EmergencyDrill>
 */
class EmergencyDrillFactory extends Factory
{
    protected $model = EmergencyDrill::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'drill_type' => 'fire',
            'conducted_at' => now(),
            'is_announced' => true,
            'expected_headcount' => 500,
            'conducted_by' => User::factory(),
        ];
    }
}
