<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\Contract;
use Modules\Core\Models\School;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'counterparty_name' => 'Example Catering Services',
            'contract_type' => 'service_provider',
            'description' => 'Tuck shop and catering services.',
            'starts_on' => now()->subMonth()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'renewal_lead_days' => 60,
            'document_file_id' => null,
            'responsible_staff_id' => null,
            'status' => 'active',
        ];
    }
}
