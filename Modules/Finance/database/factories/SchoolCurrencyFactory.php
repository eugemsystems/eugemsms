<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\SchoolCurrency;

/**
 * @extends Factory<SchoolCurrency>
 */
class SchoolCurrencyFactory extends Factory
{
    protected $model = SchoolCurrency::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'currency' => 'USD',
            'is_base' => true,
            'is_accepted_for_payment' => true,
            'rounding_increment_minor' => 1,
            'is_active' => true,
        ];
    }
}
