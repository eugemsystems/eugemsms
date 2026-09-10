<?php

declare(strict_types=1);

namespace Modules\Boarding\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Boarding\Models\LaundryCycle;
use Modules\Boarding\Models\LaundryItem;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * @extends Factory<LaundryItem>
 */
class LaundryItemFactory extends Factory
{
    protected $model = LaundryItem::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'cycle_id' => LaundryCycle::factory()->for($school),
            'student_id' => Student::factory()->for($school),
            'items_out' => 3,
            'items_back' => null,
            'missing_description' => null,
            'resolved' => false,
        ];
    }
}
