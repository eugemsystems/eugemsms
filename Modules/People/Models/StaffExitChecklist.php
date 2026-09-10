<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\StaffExitChecklistFactory;

/**
 * Book C PPL-04 §4/BR-PPL-04-021 (AC-PPL-04-008). See the migration's
 * own docblock for why this table exists despite not appearing in
 * the spec's §2 data model.
 *
 * @property int $id
 * @property int $school_id
 * @property int $staff_id
 * @property Carbon $initiated_at
 * @property int $initiated_by
 * @property array<int, array{code: string, label: string, is_cleared: bool, cleared_by: int|null, cleared_at: string|null}> $items
 * @property Carbon|null $final_pay_released_at
 * @property int|null $final_pay_released_by
 */
class StaffExitChecklist extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffExitChecklistFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @var array<int, array{code: string, label: string}>
     */
    public const array DEFAULT_ITEMS = [
        ['code' => 'keys', 'label' => 'Keys returned'],
        ['code' => 'assets', 'label' => 'School assets returned'],
        ['code' => 'laptop', 'label' => 'Laptop/device returned'],
        ['code' => 'library', 'label' => 'Library items returned'],
        ['code' => 'staff_advances', 'label' => 'Staff advances/loans settled'],
    ];

    protected $fillable = [
        'school_id', 'staff_id', 'initiated_at', 'initiated_by', 'items',
        'final_pay_released_at', 'final_pay_released_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'initiated_at' => 'datetime',
            'items' => 'array',
            'final_pay_released_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffExitChecklistFactory::new();
    }

    public function isFullyCleared(): bool
    {
        return collect($this->items)->every(fn (array $item): bool => $item['is_cleared']);
    }

    /**
     * @return array<int, string>
     */
    public function outstandingItemLabels(): array
    {
        return collect($this->items)->reject(fn (array $item): bool => $item['is_cleared'])->pluck('label')->all();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
