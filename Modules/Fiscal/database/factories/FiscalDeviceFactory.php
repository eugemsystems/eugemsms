<?php

declare(strict_types=1);

namespace Modules\Fiscal\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Fiscal\Models\FiscalDevice;

/**
 * @extends Factory<FiscalDevice>
 */
class FiscalDeviceFactory extends Factory
{
    protected $model = FiscalDevice::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'device_id' => 'DEV-'.fake()->unique()->numerify('#####'),
            'device_serial' => strtoupper(fake()->unique()->bothify('SN########')),
            'taxpayer_name' => 'Test School (Pvt) Ltd',
            'taxpayer_tin' => fake()->numerify('##########'),
            'environment' => 'sandbox',
            'api_base_url' => 'https://sandbox.fdms.zimra.co.zw',
            'taxpayer_day_max_hours' => 24,
            'status' => 'active',
            'is_active' => true,
        ];
    }

    public function production(): self
    {
        return $this->state(fn (): array => ['environment' => 'production', 'api_base_url' => 'https://fdms.zimra.co.zw']);
    }
}
