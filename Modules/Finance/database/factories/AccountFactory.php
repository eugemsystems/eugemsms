<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AccountType;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'account_type_id' => AccountType::where('code', 'ASSET')->value('id'),
            'code' => fake()->unique()->numerify('####'),
            'name' => fake()->words(3, true),
            'is_postable' => true,
            'is_control_account' => false,
            'is_system' => false,
            'requires_cost_centre' => false,
            'is_active' => true,
        ];
    }

    public function income(): self
    {
        return $this->state(fn (): array => ['account_type_id' => AccountType::where('code', 'INCOME')->value('id')]);
    }

    public function liability(): self
    {
        return $this->state(fn (): array => ['account_type_id' => AccountType::where('code', 'LIABILITY')->value('id')]);
    }

    public function expense(): self
    {
        return $this->state(fn (): array => ['account_type_id' => AccountType::where('code', 'EXPENSE')->value('id')]);
    }

    public function controlAccount(string $subledgerType): self
    {
        return $this->state(fn (): array => [
            'is_control_account' => true,
            'subledger_type' => $subledgerType,
        ]);
    }

    public function system(string $systemKey): self
    {
        return $this->state(fn (): array => [
            'is_system' => true,
            'system_key' => $systemKey,
        ]);
    }

    public function header(): self
    {
        return $this->state(fn (): array => ['is_postable' => false]);
    }
}
