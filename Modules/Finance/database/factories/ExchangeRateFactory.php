<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Models\ExchangeRateSource;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'source_id' => ExchangeRateSource::factory(),
            'from_currency' => 'ZWG',
            'to_currency' => 'USD',
            'rate' => '0.0294117647',
            'inverse_rate' => '34.0000000000',
            'effective_from' => now()->subDay(),
            'effective_to' => null,
            'status' => 'active',
            'captured_by' => User::factory(),
            'created_at' => now(),
        ];
    }
}
