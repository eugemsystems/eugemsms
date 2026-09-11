<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\BorrowerCategory;
use Modules\Core\Models\School;

/**
 * @extends Factory<BorrowerCategory>
 */
class BorrowerCategoryFactory extends Factory
{
    protected $model = BorrowerCategory::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'category' => 'secondary',
            'max_concurrent_loans' => 3,
            'loan_period_days' => 14,
            'max_renewals' => 1,
        ];
    }
}
