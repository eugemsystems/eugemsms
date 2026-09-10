<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\AllocationConstraintFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book F BRD-01 §2/§3 ⭐ — the allocation engine's configurable SOFT
 * rule set only. Hard constraints (gender, availability, room in
 * service, incompatibility, medical proximity, mobility) are
 * unconditional code, never rows here.
 *
 * @property int $id
 * @property int $school_id
 * @property string $constraint_type
 * @property string $severity
 * @property int $weight
 * @property int|null $hostel_id
 * @property array<int, int>|null $grade_level_ids
 * @property int|null $value
 * @property string|null $reason
 * @property bool $is_active
 */
class AllocationConstraint extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AllocationConstraintFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'constraint_type', 'severity', 'weight', 'hostel_id', 'grade_level_ids', 'value', 'reason', 'is_active'];

    protected function casts(): array
    {
        return [
            'grade_level_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AllocationConstraintFactory::new();
    }

    /**
     * @return BelongsTo<Hostel, $this>
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }
}
