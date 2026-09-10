<?php

declare(strict_types=1);

namespace Modules\Utilities\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Utilities\Models\Meter;
use Modules\Utilities\Models\PrepaidTokenPurchase;

/**
 * @extends Factory<PrepaidTokenPurchase>
 */
class PrepaidTokenPurchaseFactory extends Factory
{
    protected $model = PrepaidTokenPurchase::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => fn (array $attributes): int => Term::factory()->create([
                'school_id' => $attributes['school_id'],
                'academic_year_id' => AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id,
            ])->id,
            'meter_id' => fn (array $attributes): int => Meter::factory()->create(['school_id' => $attributes['school_id']])->id,
            'purchased_at' => now(),
            'token_number' => fake()->unique()->numerify('####-####-####-####'),
            'amount_paid_minor' => 20000,
            'currency' => 'USD',
            'units_purchased' => 100,
            'purchased_by' => User::factory(),
            'credit_confirmed' => false,
            'status' => 'purchased',
        ];
    }
}
