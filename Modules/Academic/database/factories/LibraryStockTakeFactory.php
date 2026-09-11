<?php

declare(strict_types=1);

namespace Modules\Academic\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academic\Models\LibraryStockTake;
use Modules\Core\Models\School;

/**
 * @extends Factory<LibraryStockTake>
 */
class LibraryStockTakeFactory extends Factory
{
    protected $model = LibraryStockTake::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'conducted_on' => now()->toDateString(),
            'expected_count' => 0,
            'scanned_count' => 0,
            'missing_count' => 0,
            'scanned_copy_ids' => [],
            'confirmatory_pass_done' => false,
            'status' => 'in_progress',
        ];
    }
}
