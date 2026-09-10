<?php

declare(strict_types=1);

namespace Modules\Sport\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Sport\Models\Award;

/**
 * @extends Factory<Award>
 */
class AwardFactory extends Factory
{
    protected $model = Award::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'award_type' => 'half_colours',
            'title' => 'Half Colours — Rugby',
            'awarded_on' => now()->toDateString(),
            'awarded_by' => User::factory(),
            'appears_on_report_card' => true,
            'appears_on_transcript' => true,
        ];
    }
}
