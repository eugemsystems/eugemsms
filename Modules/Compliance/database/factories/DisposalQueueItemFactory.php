<?php

declare(strict_types=1);

namespace Modules\Compliance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Compliance\Models\DisposalQueueItem;
use Modules\Compliance\Models\RetentionSchedule;
use Modules\Core\Models\School;

/**
 * @extends Factory<DisposalQueueItem>
 */
class DisposalQueueItemFactory extends Factory
{
    protected $model = DisposalQueueItem::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'schedule_id' => fn (array $attributes): int => RetentionSchedule::factory()->create(['school_id' => $attributes['school_id']])->id,
            'record_type' => 'application_unsuccessful',
            'record_id' => 1,
            'eligible_on' => now()->toDateString(),
            'review_status' => 'pending_review',
            'deferred_until' => null,
            'deferral_reason' => null,
            'reviewed_by' => null,
            'disposed_at' => null,
            'disposal_method' => null,
        ];
    }
}
