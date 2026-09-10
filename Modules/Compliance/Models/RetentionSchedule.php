<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Compliance\Database\Factories\RetentionScheduleFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-005/007.
 *
 * @property int $id
 * @property int $school_id
 * @property string $record_class
 * @property array<int, string> $table_names
 * @property string $retention_years
 * @property string $retention_trigger
 * @property string $disposal_method
 * @property string $legal_basis
 * @property bool $requires_review
 * @property bool $is_active
 */
class RetentionSchedule extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<RetentionScheduleFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'record_class', 'table_names', 'retention_years', 'retention_trigger',
        'disposal_method', 'legal_basis', 'requires_review', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'table_names' => 'array',
            'retention_years' => 'decimal:2',
            'requires_review' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RetentionScheduleFactory::new();
    }

    /**
     * @return HasMany<DisposalQueueItem, $this>
     */
    public function disposalQueueItems(): HasMany
    {
        return $this->hasMany(DisposalQueueItem::class, 'schedule_id');
    }
}
