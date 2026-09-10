<?php

declare(strict_types=1);

namespace Modules\Transport\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\People\Models\Student;
use Modules\Transport\Models\LearnerTransport;
use Modules\Transport\Models\Route;
use Modules\Transport\Models\RouteStop;
use Modules\Transport\Models\TransportZone;

/**
 * @extends Factory<LearnerTransport>
 */
class LearnerTransportFactory extends Factory
{
    protected $model = LearnerTransport::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => fn (array $attributes): int => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            'term_id' => fn (array $attributes): int => Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $attributes['academic_year_id']])->id,
            'student_id' => fn (array $attributes): int => Student::factory()->create(['school_id' => $attributes['school_id']])->id,
            'route_id' => fn (array $attributes): int => Route::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $attributes['academic_year_id']])->id,
            'pickup_stop_id' => fn (array $attributes): int => RouteStop::factory()->create(['school_id' => $attributes['school_id'], 'route_id' => $attributes['route_id']])->id,
            'zone_id' => fn (array $attributes): int => TransportZone::factory()->create(['school_id' => $attributes['school_id']])->id,
            'direction' => 'both',
            'effective_from' => now()->toDateString(),
            'status' => 'active',
            'billing_status' => 'pending',
            'authorised_by_guardian' => true,
        ];
    }
}
