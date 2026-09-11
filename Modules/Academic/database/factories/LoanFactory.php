<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\Loan;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        $copy = LibraryCopy::factory()->create();
        $term = Term::factory()->for($copy->school)->create();

        return [
            'school_id' => $copy->school_id,
            'term_id' => $term->id,
            'copy_id' => $copy->id,
            'borrower_type' => 'student',
            'borrower_id' => Student::factory()->create(['school_id' => $copy->school_id])->id,
            'borrower_category' => 'secondary',
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(14)->toDateString(),
            'renewal_count' => 0,
            'status' => 'active',
        ];
    }
}
