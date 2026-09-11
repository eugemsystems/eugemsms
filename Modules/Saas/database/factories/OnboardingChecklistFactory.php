<?php

declare(strict_types=1);

namespace Modules\Saas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Models\OnboardingChecklist;

/**
 * @extends Factory<OnboardingChecklist>
 */
class OnboardingChecklistFactory extends Factory
{
    protected $model = OnboardingChecklist::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'school_id' => School::factory(),
            'started_at' => Carbon::now(),
            'target_go_live_date' => Carbon::today()->addMonth()->toDateString(),
            'steps' => [
                ['key' => 'data_import', 'label' => 'Import student data', 'completed_at' => null, 'owner' => null],
                ['key' => 'staff_training', 'label' => 'Staff training session', 'completed_at' => null, 'owner' => null],
            ],
            'status' => 'in_progress',
            'assigned_success_manager' => null,
        ];
    }

    public function status(string $status): self
    {
        return $this->state(['status' => $status]);
    }
}
