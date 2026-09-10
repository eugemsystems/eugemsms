<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Receipt;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    protected $model = Receipt::class;

    public function definition(): array
    {
        $school = School::factory()->create();
        $year = AcademicYear::factory()->for($school)->create();

        return [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear')->create()->id,
            'receipt_number' => 'RCT/'.fake()->unique()->numerify('######'),
            'receipt_type' => 'fee',
            'payer_type' => 'guardian',
            'payer_name' => fake()->name(),
            'amount_minor' => 10000,
            'currency' => 'USD',
            'base_amount_minor' => 10000,
            'received_at' => now(),
            'effective_date' => now()->toDateString(),
            'status' => 'posted',
            'received_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
