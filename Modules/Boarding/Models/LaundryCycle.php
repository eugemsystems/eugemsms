<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Boarding\Database\Factories\LaundryCycleFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

/**
 * Book F BRD-05 §2/BR-BRD-05-005/007.
 *
 * @property int $id
 * @property int $school_id
 * @property int $term_id
 * @property int $hostel_id
 * @property Carbon $cycle_date
 * @property Carbon|null $collected_at
 * @property Carbon|null $returned_at
 * @property int $items_collected
 * @property int $items_returned
 * @property int $items_missing
 * @property string $status
 * @property int|null $cost_minor
 * @property int|null $supervised_by
 */
class LaundryCycle extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LaundryCycleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'hostel_id', 'cycle_date', 'collected_at', 'returned_at',
        'items_collected', 'items_returned', 'items_missing', 'status', 'cost_minor', 'supervised_by',
    ];

    protected function casts(): array
    {
        return [
            'cycle_date' => 'date',
            'collected_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LaundryCycleFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Hostel, $this>
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function supervisedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'supervised_by');
    }

    /**
     * @return HasMany<LaundryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LaundryItem::class, 'cycle_id');
    }
}
