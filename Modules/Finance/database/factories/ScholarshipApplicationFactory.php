<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\ScholarshipApplication;
use Modules\People\Models\Student;

/**
 * @extends Factory<ScholarshipApplication>
 */
class ScholarshipApplicationFactory extends Factory
{
    protected $model = ScholarshipApplication::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'scheme_id' => DiscountScheme::factory()->schemeType('application_based'),
            'student_id' => Student::factory(),
            'applied_by_guardian_id' => null,
            'household_income_band' => null,
            'supporting_document_ids' => null,
            'means_assessment_score' => null,
            'academic_average_at_application' => null,
            'narrative' => fake()->paragraph(),
            'status' => 'submitted',
            'committee_notes' => null,
            'decided_by' => null,
            'decided_at' => null,
            'rejection_reason' => null,
        ];
    }

    public function status(string $status): self
    {
        return $this->state(['status' => $status]);
    }
}
