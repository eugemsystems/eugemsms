<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\NumberingSeries;
use Modules\Core\Models\School;

/**
 * @extends Factory<NumberingSeries>
 */
class NumberingSeriesFactory extends Factory
{
    protected $model = NumberingSeries::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'document_type' => 'receipt',
            'pattern' => '{SCHOOL}/{TYPE}/{SEQ:6}',
            'prefix' => null,
            'next_sequence' => 1,
            'sequence_padding' => 6,
            'reset_policy' => 'never',
            'is_active' => true,
        ];
    }
}
