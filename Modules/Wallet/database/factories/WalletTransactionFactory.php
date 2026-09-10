<?php

declare(strict_types=1);

namespace Modules\Wallet\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Wallet\Models\StudentWallet;
use Modules\Wallet\Models\WalletTransaction;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    protected $model = WalletTransaction::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'term_id' => function (array $attributes): int {
                $year = AcademicYear::factory()->create(['school_id' => $attributes['school_id']]);

                return Term::factory()->create(['school_id' => $attributes['school_id'], 'academic_year_id' => $year->id])->id;
            },
            'wallet_id' => fn (array $attributes): int => StudentWallet::factory()->create(['school_id' => $attributes['school_id']])->id,
            'transaction_type' => 'topup',
            'direction' => 'in',
            'amount_minor' => 1000,
            'balance_after_minor' => 1000,
            'currency' => 'USD',
            'occurred_at' => now(),
        ];
    }
}
