<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\IssuableItem;
use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<LearnerIssuedItem>
 */
class LearnerIssuedItemFactory extends Factory
{
    protected $model = LearnerIssuedItem::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'student_id' => Student::factory()->for($school),
            'issuable_item_id' => IssuableItem::factory()->for($school),
            'tag_reference' => null,
            'quantity' => 1,
            'condition_at_issue' => 'new',
            'issued_on' => now()->toDateString(),
            'issued_by' => User::factory(),
            'returned_on' => null,
            'condition_at_return' => null,
            'received_by' => null,
            'status' => 'issued',
            'charge_minor' => null,
            'ad_hoc_charge_id' => null,
            'notes' => null,
        ];
    }
}
