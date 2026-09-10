<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\Appeal;
use Modules\Welfare\Models\Sanction;

/**
 * @extends Factory<Appeal>
 */
class AppealFactory extends Factory
{
    protected $model = Appeal::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'sanction_id' => Sanction::factory()->create(['school_id' => $school]),
            'lodged_by_student' => false,
            'grounds' => 'The learner disputes the account of events.',
            'lodged_at' => now(),
            'status' => 'lodged',
        ];
    }
}
