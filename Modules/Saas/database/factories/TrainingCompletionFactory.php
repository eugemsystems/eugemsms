<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\School;
use Modules\Saas\Models\TrainingCompletion;

/**
 * @extends Factory<TrainingCompletion>
 */
class TrainingCompletionFactory extends Factory
{
    protected $model = TrainingCompletion::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'user_id' => User::factory(),
            'material_key' => 'finance_module_basics',
            'completed_at' => Carbon::now(),
        ];
    }
}
