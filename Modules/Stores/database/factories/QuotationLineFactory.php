<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Stores\Models\Quotation;
use Modules\Stores\Models\QuotationLine;

/**
 * @extends Factory<QuotationLine>
 */
class QuotationLineFactory extends Factory
{
    protected $model = QuotationLine::class;

    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'description' => 'Photocopy paper, A4, 80gsm',
            'quantity' => 20,
            'unit_price_minor' => 5000,
            'line_total_minor' => 100000,
        ];
    }
}
