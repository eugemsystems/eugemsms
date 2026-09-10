<?php

declare(strict_types=1);

namespace Modules\Finance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Finance\Models\CreditNote;
use Modules\Finance\Models\CreditNoteLine;
use Modules\Finance\Models\FeeComponent;

/**
 * @extends Factory<CreditNoteLine>
 */
class CreditNoteLineFactory extends Factory
{
    protected $model = CreditNoteLine::class;

    public function definition(): array
    {
        return [
            'credit_note_id' => CreditNote::factory(),
            'component_id' => FeeComponent::factory(),
            'description' => 'Adjustment',
            'amount_minor' => 1000,
            'currency' => 'USD',
        ];
    }
}
