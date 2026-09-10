<?php

declare(strict_types=1);

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Security\Models\Contractor;
use Modules\Security\Models\ContractorWorker;

/**
 * @extends Factory<ContractorWorker>
 */
class ContractorWorkerFactory extends Factory
{
    protected $model = ContractorWorker::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'contractor_id' => fn (array $attributes): int => Contractor::factory()->create(['school_id' => $attributes['school_id']])->id,
            'full_name' => 'John Worker',
            'is_cleared' => false,
        ];
    }

    public function cleared(): self
    {
        return $this->state(fn (): array => [
            'induction_completed_on' => now()->toDateString(),
            'police_clearance_on' => now()->toDateString(),
            'is_cleared' => true,
        ]);
    }
}
