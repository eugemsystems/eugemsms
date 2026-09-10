<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\MealServiceFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book F BRD-04 §2/§3 ⭐ — one meal, one day, actually served.
 * `present_boarders` is the number that drives servings
 * (BR-BRD-04-001), read from `BRD-02`'s `LiveOccupancyProvider`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property Carbon $service_date
 * @property string $meal
 * @property int|null $menu_day_id
 * @property int $nominal_boarders
 * @property int $present_boarders
 * @property int $on_exeat
 * @property int $in_sick_bay
 * @property int $staff_meals
 * @property int $guest_meals
 * @property int $planned_servings
 * @property int|null $actual_served
 * @property int|null $issued_cost_minor
 * @property string $currency
 * @property int|null $cost_per_serving_minor
 * @property string|null $wastage_note
 * @property string $status
 * @property int|null $requisition_id
 * @property int|null $prepared_by_staff_id
 */
class MealService extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MealServiceFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'service_date', 'meal', 'menu_day_id', 'nominal_boarders',
        'present_boarders', 'on_exeat', 'in_sick_bay', 'staff_meals', 'guest_meals',
        'planned_servings', 'actual_served', 'issued_cost_minor', 'currency',
        'cost_per_serving_minor', 'wastage_note', 'status', 'requisition_id', 'prepared_by_staff_id',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MealServiceFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<MenuDay, $this>
     */
    public function menuDay(): BelongsTo
    {
        return $this->belongsTo(MenuDay::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'prepared_by_staff_id');
    }

    /**
     * @return HasMany<MealRequisitionLine, $this>
     */
    public function requisitionLines(): HasMany
    {
        return $this->hasMany(MealRequisitionLine::class, 'meal_service_id');
    }
}
