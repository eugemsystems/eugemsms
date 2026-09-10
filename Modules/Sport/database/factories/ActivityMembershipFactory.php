<?php

declare(strict_types=1);

namespace Modules\Sport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Sport\Models\Activity;
use Modules\Sport\Models\ActivityMembership;

/**
 * @extends Factory<ActivityMembership>
 */
class ActivityMembershipFactory extends Factory
{
    protected $model = ActivityMembership::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'term_id' => fn (array $attributes): int => Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $attributes['academic_year_id']])->id,
            'activity_id' => fn (array $attributes): int => Activity::factory()->create(['school_id' => $attributes['school_id']])->id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'joined_on' => now()->toDateString(),
            'consent_received' => false,
            'billing_status' => 'pending',
            'status' => 'active',
        ];
    }
}
