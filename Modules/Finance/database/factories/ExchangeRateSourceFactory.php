<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\ExchangeRateSource;

/**
 * @extends Factory<ExchangeRateSource>
 */
class ExchangeRateSourceFactory extends Factory
{
    protected $model = ExchangeRateSource::class;

    public function definition(): array
    {
        return [
            'school_id' => null,
            'key' => 'manual',
            'name' => 'Manually Captured',
            'is_automatic' => false,
            'requires_approval' => false,
            'priority' => 0,
            'is_active' => true,
        ];
    }
}
