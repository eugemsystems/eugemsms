<?php

declare(strict_types=1);

namespace Modules\Fiscal\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Fiscal\Models\FiscalDay;
use Modules\Fiscal\Models\FiscalDevice;
use Modules\Fiscal\Models\FiscalZReport;

/**
 * @extends Factory<FiscalZReport>
 */
class FiscalZReportFactory extends Factory
{
    protected $model = FiscalZReport::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'device_id' => fn (array $attributes): int => FiscalDevice::factory()->create(['school_id' => $attributes['school_id']])->id,
            'fiscal_day_id' => fn (array $attributes): int => FiscalDay::factory()->create(['school_id' => $attributes['school_id'], 'device_id' => $attributes['device_id']])->id,
            'report_date' => now()->toDateString(),
            'receipt_count' => 0,
            'totals_by_currency' => [],
            'totals_by_tax_type' => [],
            'totals_by_payment' => [],
            'status' => 'pending',
        ];
    }
}
