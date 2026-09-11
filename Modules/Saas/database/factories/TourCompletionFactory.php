<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Saas\Models\ProductTour;
use Modules\Saas\Models\TourCompletion;

/**
 * @extends Factory<TourCompletion>
 */
class TourCompletionFactory extends Factory
{
    protected $model = TourCompletion::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tour_key' => fn (): string => ProductTour::factory()->create()->key,
            'completed_at' => null,
            'skipped_at' => null,
        ];
    }
}
