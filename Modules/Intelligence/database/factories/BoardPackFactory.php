<?php

declare(strict_types=1);

namespace Modules\Intelligence\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Intelligence\Models\BoardPack;

/**
 * @extends Factory<BoardPack>
 */
class BoardPackFactory extends Factory
{
    protected $model = BoardPack::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'sections_included' => ['enrolment', 'financial'],
            'document_id' => null,
            'generated_by' => User::factory(),
            'generated_at' => now(),
        ];
    }
}
