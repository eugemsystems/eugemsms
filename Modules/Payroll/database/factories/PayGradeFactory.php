<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Payroll\Models\PayGrade;

/**
 * @extends Factory<PayGrade>
 */
class PayGradeFactory extends Factory
{
    protected $model = PayGrade::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'GR-'.fake()->unique()->numerify('###'),
            'name' => 'Grade '.fake()->numerify('#'),
            'category' => 'teaching',
            'currency' => 'USD',
            'is_active' => true,
        ];
    }
}
