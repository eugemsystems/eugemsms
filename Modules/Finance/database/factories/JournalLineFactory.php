<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Journal;
use Modules\Finance\Models\JournalLine;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    public function definition(): array
    {
        return [
            'school_id' => fn (array $attrs): ?int => Journal::query()->whereKey($attrs['journal_id'])->value('school_id'),
            'journal_id' => Journal::factory(),
            'line_number' => 1,
            'account_id' => Account::factory(),
            'direction' => 'DR',
            'amount_minor' => 10000,
            'currency' => 'USD',
            'base_amount_minor' => 10000,
            'base_currency' => 'USD',
            'exchange_rate' => '1.0000000000',
            'effective_at' => now()->toDateString(),
            'term_id' => fn (array $attrs): ?int => Journal::query()->whereKey($attrs['journal_id'])->value('term_id'),
            'created_at' => now(),
        ];
    }
}
