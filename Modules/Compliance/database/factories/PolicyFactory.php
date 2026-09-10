<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\Policy;
use Modules\Core\Models\School;

/**
 * @extends Factory<Policy>
 */
class PolicyFactory extends Factory
{
    protected $model = Policy::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'code' => 'CHILD-PROTECTION',
            'title' => 'Child Protection Policy',
            'category' => 'safeguarding',
            'version' => 'v1',
            'content' => 'Policy content.',
            'document_file_id' => null,
            'effective_from' => now()->toDateString(),
            'review_due_on' => now()->addYear()->toDateString(),
            'approved_by' => null,
            'board_approved_on' => null,
            'requires_acknowledgement' => true,
            'acknowledgement_audiences' => ['staff'],
            'status' => 'active',
            'supersedes_policy_id' => null,
        ];
    }
}
