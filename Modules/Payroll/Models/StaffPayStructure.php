<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Payroll\Database\Factories\StaffPayStructureFactory;

/**
 * Book H3 PPL-05 §2 ⭐ — a staff member has at most one `active`
 * structure at a time, enforced by `CreateStaffPayStructureAction`,
 * mirroring PPL-04's one-active-contract rule.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $staff_id
 * @property int|null $contract_id
 * @property int|null $grade_id
 * @property string|null $notch
 * @property string $primary_currency
 * @property string|null $usd_portion_percent
 * @property string|null $zwg_portion_percent
 * @property string $payment_currency
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $status
 */
class StaffPayStructure extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffPayStructureFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'staff_id', 'contract_id', 'grade_id', 'notch', 'primary_currency',
        'usd_portion_percent', 'zwg_portion_percent', 'payment_currency', 'effective_from',
        'effective_to', 'status', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'usd_portion_percent' => 'decimal:2',
            'zwg_portion_percent' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffPayStructureFactory::new();
    }

    /**
     * @return HasMany<StaffPayComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(StaffPayComponent::class, 'pay_structure_id');
    }
}
