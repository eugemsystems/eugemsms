<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Models\CostCentre;
use Modules\People\Database\Factories\DepartmentFactory;

/**
 * Book C PPL-04 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int|null $head_staff_id
 * @property int|null $cost_centre_id
 * @property bool $is_active
 */
class Department extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'parent_id', 'code', 'name', 'type', 'head_staff_id', 'cost_centre_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DepartmentFactory::new();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function head(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'head_staff_id');
    }

    /**
     * @return BelongsTo<CostCentre, $this>
     */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /**
     * @return HasMany<Staff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * @return HasMany<EstablishmentPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(EstablishmentPost::class);
    }
}
