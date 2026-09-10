<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\ApprovalChain;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\School;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'chain_id' => ApprovalChain::factory(),
            'approvable_type' => 'purchase_order',
            'approvable_id' => 1,
            'current_step' => 1,
            'status' => 'pending',
            'title' => 'Purchase order #1',
            'requested_by' => User::factory(),
            'requested_at' => now(),
        ];
    }
}
