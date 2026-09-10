<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\ZimsecRegistration;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * @extends Factory<ZimsecRegistration>
 */
class ZimsecRegistrationFactory extends Factory
{
    protected $model = ZimsecRegistration::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'examination_session_id' => null,
            'exam_level' => 'o_level',
            'exam_series' => 'November '.now()->format('Y'),
            'centre_number' => (string) $this->faker->unique()->numerify('#####'),
            'registration_opens_on' => now()->toDateString(),
            'registration_closes_on' => now()->addMonth()->toDateString(),
            'candidate_count' => 0,
            'validated_count' => 0,
            'error_count' => 0,
            'total_fees_minor' => 0,
            'collected_minor' => 0,
            'remitted_minor' => 0,
            'currency' => 'USD',
            'export_file_id' => null,
            'status' => 'preparing',
            'submitted_at' => null,
            'submitted_by' => null,
            'zimsec_reference' => null,
        ];
    }
}
