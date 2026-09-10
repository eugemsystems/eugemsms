<?php

declare(strict_types=1);

namespace Modules\Operations\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Operations\Database\Factories\WorkOrderLabourFactory;
use Modules\People\Models\Staff;

/**
 * @property int $id
 * @property int $school_id
 * @property int $work_order_id
 * @property int $staff_id
 * @property Carbon $work_date
 * @property float $hours
 * @property int|null $hourly_rate_minor
 * @property int|null $cost_minor
 */
class WorkOrderLabour extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WorkOrderLabourFactory> */
    use HasFactory;

    protected $table = 'work_order_labour';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'work_order_id', 'staff_id', 'work_date', 'hours', 'hourly_rate_minor', 'cost_minor',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WorkOrderLabourFactory::new();
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
