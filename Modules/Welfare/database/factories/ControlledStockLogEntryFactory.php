<?php

declare(strict_types=1);

namespace Modules\Welfare\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\School;
use Modules\Welfare\Models\ClinicStock;
use Modules\Welfare\Models\ControlledStockLogEntry;

/**
 * @extends Factory<ControlledStockLogEntry>
 */
class ControlledStockLogEntryFactory extends Factory
{
    protected $model = ControlledStockLogEntry::class;

    public function definition(): array
    {
        $school = School::factory();

        return [
            'school_id' => $school,
            'clinic_stock_id' => ClinicStock::factory()->create(['school_id' => $school, 'is_controlled' => true]),
            'action' => 'received',
            'quantity' => 10,
            'balance_after' => 10,
            'performed_by' => User::factory(),
            'witnessed_by' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
