<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Security\Models\ContractorSiteVisit;
use Modules\Security\Models\ContractorWorker;

/**
 * @extends Factory<ContractorSiteVisit>
 */
class ContractorSiteVisitFactory extends Factory
{
    protected $model = ContractorSiteVisit::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'contractor_worker_id' => fn (array $attributes): int => ContractorWorker::factory()->create(['school_id' => $attributes['school_id']])->id,
            'signed_in_at' => now(),
            'gate_staff_in' => User::factory(),
        ];
    }
}
