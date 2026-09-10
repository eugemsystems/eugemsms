<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\ReconciliationRun;

/**
 * @extends Factory<ReconciliationRun>
 */
class ReconciliationRunFactory extends Factory
{
    protected $model = ReconciliationRun::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'run_date' => now()->toDateString(),
            'scope' => 'full',
            'currency' => 'USD',
            'status' => 'clean',
            'ran_at' => now(),
        ];
    }
}
