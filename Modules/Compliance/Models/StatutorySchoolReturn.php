<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\StatutorySchoolReturnFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * Book H3 CMP-02 §2/BR-CMP-02-001 ⭐. `data_snapshot` is frozen at
 * generation — never mutated once set. `due_date` stays mutable: MoPSE
 * can and does revise a deadline after it's published.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $return_type
 * @property string $period_reference
 * @property Carbon $due_date
 * @property string $authority
 * @property array<string, mixed> $data_snapshot
 * @property array<string, mixed>|null $validation_result
 * @property int $quality_issues
 * @property int|null $export_file_id
 * @property string $status
 * @property int|null $generated_by
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property string|null $acknowledgement_ref
 */
class StatutorySchoolReturn extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StatutorySchoolReturnFactory> */
    use HasFactory;

    use HasUlid;

    private const array MUTABLE_AFTER_CREATE = [
        'due_date', 'validation_result', 'quality_issues', 'export_file_id', 'status',
        'submitted_at', 'submitted_by', 'acknowledgement_ref', 'updated_at',
    ];

    protected $fillable = [
        'school_id', 'return_type', 'period_reference', 'due_date', 'authority', 'data_snapshot',
        'validation_result', 'quality_issues', 'export_file_id', 'status', 'generated_by',
        'submitted_at', 'submitted_by', 'acknowledgement_ref',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'data_snapshot' => 'array',
            'validation_result' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StatutorySchoolReturnFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'A statutory_school_returns row may only change validation_result, quality_issues, export_file_id, status, submitted_at, submitted_by, or acknowledgement_ref after creation — data_snapshot is frozen (BR-CMP-02-001).',
                    ['dirty' => $illegal],
                );
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
