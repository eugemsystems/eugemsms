<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Reporting\Models\CloseCheckAcknowledgement;
use Modules\Reporting\Models\PeriodCloseChecklist;

/**
 * @extends Factory<CloseCheckAcknowledgement>
 */
class CloseCheckAcknowledgementFactory extends Factory
{
    protected $model = CloseCheckAcknowledgement::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'checklist_id' => fn (array $attributes): int => PeriodCloseChecklist::factory()->create(['school_id' => $attributes['school_id']])->id,
            'check_key' => 'fin04_suspense_balance',
            'reason' => 'Awaiting bank confirmation of the deposit source.',
            'acknowledged_by' => User::factory(),
            'acknowledged_at' => now(),
        ];
    }
}
