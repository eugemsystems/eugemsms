<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Boarding\Database\Factories\ExeatTypeFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book F BRD-03 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property int|null $max_duration_hours
 * @property bool $requires_guardian_request
 * @property bool $requires_document
 * @property int|null $approval_chain_id
 * @property int $min_notice_hours
 * @property int|null $allowed_per_term
 * @property bool $counts_toward_quota
 * @property bool $blocks_on_fee_arrears
 * @property bool $blocks_on_suspension
 * @property bool $is_active
 */
class ExeatType extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExeatTypeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'max_duration_hours', 'requires_guardian_request',
        'requires_document', 'approval_chain_id', 'min_notice_hours', 'allowed_per_term',
        'counts_toward_quota', 'blocks_on_fee_arrears', 'blocks_on_suspension', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_guardian_request' => 'boolean',
            'requires_document' => 'boolean',
            'counts_toward_quota' => 'boolean',
            'blocks_on_fee_arrears' => 'boolean',
            'blocks_on_suspension' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExeatTypeFactory::new();
    }
}
