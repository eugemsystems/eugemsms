<?php

declare(strict_types=1);

namespace Modules\People\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Database\Factories\StudentAttributeChangeFactory;

/**
 * Book C PPL-01 §3 ⭐/BR-PPL-01-004. Append-only — the billing truth
 * `FIN-02` prorates against. `rebilling_status` is the sole exception,
 * updated once a rebilling dispatch completes or fails (BR-PPL-01-006).
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $attribute
 * @property string|null $old_value
 * @property string $new_value
 * @property Carbon $effective_from
 * @property string|null $reason_code
 * @property string|null $reason
 * @property bool $triggers_rebilling
 * @property string|null $rebilling_status
 * @property int $changed_by
 * @property Carbon $changed_at
 */
class StudentAttributeChange extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentAttributeChangeFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'academic_year_id', 'term_id', 'attribute',
        'old_value', 'new_value', 'effective_from', 'reason_code', 'reason',
        'triggers_rebilling', 'rebilling_status', 'changed_by', 'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'triggers_rebilling' => 'boolean',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentAttributeChangeFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());

            if (array_diff($dirty, ['rebilling_status']) !== []) {
                throw new InvalidStateTransitionException(
                    'student_attribute_changes permits updating rebilling_status only — every other column is immutable.',
                    ['dirty' => $dirty],
                );
            }
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('student_attribute_changes is append-only and can never be deleted.');
        });
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
