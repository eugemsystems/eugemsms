<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelDamage;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<HostelDamage>
 */
class HostelDamageFactory extends Factory
{
    protected $model = HostelDamage::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'hostel_id' => Hostel::factory()->for($school),
            'damage_type' => 'window',
            'description' => 'Broken window pane in the dormitory.',
            'currency' => 'USD',
            'liability' => 'shared_room',
            'charge_status' => 'pending',
            'reported_by' => User::factory(),
            'reported_at' => now(),
        ];
    }
}
