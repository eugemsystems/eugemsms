<?php

declare(strict_types=1);

namespace Modules\Wallet\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Wallet\Models\SpendPoint;
use Modules\Wallet\Models\WalletSale;

/**
 * @extends Factory<WalletSale>
 */
class WalletSaleFactory extends Factory
{
    protected $model = WalletSale::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => function (array $attributes): int {
                $year = AcademicYear::factory()->create(['school_id' => $attributes['school_id']]);

                return Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $year->id])->id;
            },
            'spend_point_id' => fn (array $attributes): int => SpendPoint::factory()->create(['school_id' => $attributes['school_id']])->id,
            'sale_number' => 'WS-'.fake()->unique()->numerify('######'),
            'sold_at' => now(),
            'subtotal_minor' => 100,
            'total_minor' => 100,
            'currency' => 'USD',
            'payment_method' => 'wallet',
            'operator_id' => User::factory(),
            'device_source' => 'pos',
            'status' => 'completed',
        ];
    }
}
