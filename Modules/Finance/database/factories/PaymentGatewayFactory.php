<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\PaymentGateway;

/**
 * @extends Factory<PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
{
    protected $model = PaymentGateway::class;

    public function definition(): array
    {
        $school = School::factory()->create();

        return [
            'school_id' => $school->id,
            'driver' => 'fake',
            'name' => 'Fake Gateway',
            'credentials' => json_encode(['key' => 'test']),
            'supported_methods' => ['ecocash', 'visa'],
            'supported_currencies' => ['USD'],
            'settlement_account_id' => Account::factory()->for($school)->create()->id,
            'fee_account_id' => Account::factory()->for($school)->create()->id,
            'is_default' => true,
            'is_sandbox' => true,
            'is_active' => true,
            'health_status' => 'up',
        ];
    }
}
