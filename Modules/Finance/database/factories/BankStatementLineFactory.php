<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\BankStatement;
use Modules\Finance\Models\BankStatementLine;

/**
 * @extends Factory<BankStatementLine>
 */
class BankStatementLineFactory extends Factory
{
    protected $model = BankStatementLine::class;

    public function definition(): array
    {
        return [
            'school_id' => fn (array $attrs): ?int => BankStatement::query()->whereKey($attrs['statement_id'])->value('school_id'),
            'statement_id' => BankStatement::factory(),
            'line_number' => 1,
            'transaction_date' => now()->toDateString(),
            'description' => 'BANK TRANSFER',
            'credit_minor' => 10000,
            'currency' => 'USD',
            'match_status' => 'unmatched',
        ];
    }
}
