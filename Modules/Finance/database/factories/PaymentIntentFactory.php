<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\PaymentGateway;
use Modules\Finance\Models\PaymentIntent;
use Modules\People\Models\Student;

/**
 * @extends Factory<PaymentIntent>
 */
class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    public function definition(): array
    {
        $school = School::factory()->create();
        $year = AcademicYear::factory()->for($school)->create();

        return [
            'school_id' => $school->id,
            'academic_year_id' => $year->id,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear')->create()->id,
            'gateway_id' => PaymentGateway::factory()->create(['school_id' => $school->id])->id,
            'reference' => 'PI/'.fake()->unique()->numerify('######'),
            'idempotency_key' => (string) Str::uuid(),
            'student_id' => Student::factory()->for($school)->create()->id,
            'payer_name' => fake()->name(),
            'purpose' => 'fees',
            'amount_minor' => 10000,
            'currency' => 'USD',
            'status' => 'created',
            'initiated_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
