<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\LeaveTypeFactory;

/**
 * Book C PPL-04 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string|null $annual_entitlement_days
 * @property string $accrual_method
 * @property bool $is_paid
 * @property bool $requires_document
 * @property bool $requires_cover
 * @property bool $is_active
 */
class LeaveType extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'annual_entitlement_days', 'accrual_method', 'is_paid',
        'requires_document', 'max_consecutive_days', 'carry_forward_days', 'requires_cover',
        'applies_to_categories', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'annual_entitlement_days' => 'decimal:1',
            'is_paid' => 'boolean',
            'requires_document' => 'boolean',
            'carry_forward_days' => 'decimal:1',
            'requires_cover' => 'boolean',
            'applies_to_categories' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LeaveTypeFactory::new();
    }
}
