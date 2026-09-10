<?php

declare(strict_types=1);

namespace Modules\People\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\People\Models\StudentAttributeChange;

/**
 * @extends Factory<StudentAttributeChange>
 */
class StudentAttributeChangeFactory extends Factory
{
    protected $model = StudentAttributeChange::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'term_id' => Term::factory(),
            'attribute' => 'residency',
            'old_value' => 'BOARDER',
            'new_value' => 'DAY',
            'effective_from' => now()->toDateString(),
            'triggers_rebilling' => true,
            'rebilling_status' => 'pending',
            'changed_by' => User::factory(),
            'changed_at' => now(),
        ];
    }
}
