<?php

declare(strict_types=1);

namespace Modules\Stores\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockMovement;
use Modules\Stores\Models\Store;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $school = School::factory();
        $year = AcademicYear::factory()->for($school);

        return [
            'school_id' => $school,
            'academic_year_id' => $year,
            'term_id' => Term::factory()->for($school)->for($year, 'academicYear'),
            'store_id' => Store::factory()->for($school),
            'item_id' => InventoryItem::factory()->for($school),
            'movement_type' => 'receipt',
            'direction' => 'in',
            'quantity' => 100,
            'unit_cost_minor' => 80,
            'total_cost_minor' => 8000,
            'currency' => 'USD',
            'base_total_minor' => 8000,
            'balance_after' => 100,
            'performed_by' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
