<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\ZimsecCandidate;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<ZimsecCandidate>
 */
class ZimsecCandidateFactory extends Factory
{
    protected $model = ZimsecCandidate::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'registration_id' => fn (array $attributes): int => ZimsecRegistration::factory()->create(['school_id' => $attributes['school_id']])->id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'candidate_number' => null,
            'surname' => $this->faker->lastName(),
            'forenames' => $this->faker->firstName(),
            'date_of_birth' => $this->faker->dateTimeBetween('-18 years', '-15 years')->format('Y-m-d'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'national_registration_no' => $this->faker->numerify('##-######-?-##'),
            'national_registration_no_hash' => null,
            'birth_certificate_no' => null,
            'birth_certificate_no_hash' => null,
            'subject_entries' => [
                ['code' => '4021', 'name' => 'English Language', 'is_resit' => false],
                ['code' => '4028', 'name' => 'Mathematics', 'is_resit' => false],
            ],
            'subject_count' => 2,
            'is_repeat_candidate' => false,
            'previous_candidate_no' => null,
            'special_arrangements' => null,
            'entry_fee_minor' => 2000,
            'currency' => 'USD',
            'ad_hoc_charge_id' => null,
            'fee_paid' => false,
            'validation_status' => 'pending',
            'validation_errors' => null,
            'statement_of_entry_id' => null,
            'statement_confirmed' => false,
            'status' => 'draft',
        ];
    }
}
