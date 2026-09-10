<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\MealRequisitionLineFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book F BRD-04 §2/§3/§4 ⭐. `unit_cost_minor`/`line_cost_minor` stay
 * null in planning-only mode (no `FIN-09`) — never displayed as zero.
 *
 * @property int $id
 * @property int $school_id
 * @property int $meal_service_id
 * @property int $inventory_item_id
 * @property string $required_quantity
 * @property string|null $issued_quantity
 * @property string|null $returned_quantity
 * @property string|null $wasted_quantity
 * @property string $unit
 * @property int|null $unit_cost_minor
 * @property int|null $line_cost_minor
 * @property string|null $substitution_note
 */
class MealRequisitionLine extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MealRequisitionLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'meal_service_id', 'inventory_item_id', 'required_quantity', 'issued_quantity',
        'returned_quantity', 'wasted_quantity', 'unit', 'unit_cost_minor', 'line_cost_minor', 'substitution_note',
    ];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MealRequisitionLineFactory::new();
    }

    /**
     * @return BelongsTo<MealService, $this>
     */
    public function mealService(): BelongsTo
    {
        return $this->belongsTo(MealService::class, 'meal_service_id');
    }
}
