<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\CurrencyConversion;
use Modules\Finance\Models\ExchangeRate;

/**
 * @extends Factory<CurrencyConversion>
 */
class CurrencyConversionFactory extends Factory
{
    protected $model = CurrencyConversion::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'context_type' => 'journal_line',
            'from_currency' => 'ZWG',
            'from_amount_minor' => 3400000,
            'to_currency' => 'USD',
            'to_amount_minor' => 10000,
            'exchange_rate_id' => ExchangeRate::factory(),
            'rate_used' => '0.0294117647',
            'rate_effective_from' => now()->subDay(),
            'converted_at' => now(),
        ];
    }
}
