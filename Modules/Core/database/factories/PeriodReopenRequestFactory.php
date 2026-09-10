<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\PeriodReopenRequest;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * @extends Factory<PeriodReopenRequest>
 */
class PeriodReopenRequestFactory extends Factory
{
    protected $model = PeriodReopenRequest::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => Term::factory(),
            'period_type' => PeriodType::Financial,
            'reason' => 'A late correction was discovered after close and needs posting.',
            'requested_by' => User::factory(),
            'requested_at' => now(),
            'status' => 'pending',
        ];
    }
}
