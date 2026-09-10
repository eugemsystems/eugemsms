<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Finance\Database\Factories\AdHocChargeFactory;
use Modules\People\Models\Student;

/**
 * Book B FIN-02 §2. A one-off, non-structure charge — a library fine,
 * a damage bill, a uniform sale.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property int $student_id
 * @property int $component_id
 * @property string $description
 * @property string $quantity
 * @property int $unit_rate_minor
 * @property int $amount_minor
 * @property string $currency
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string $status
 * @property int|null $invoice_id
 * @property int $raised_by
 * @property int|null $approved_by
 * @property Carbon $created_at
 */
class AdHocCharge extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AdHocChargeFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    private const array MUTABLE_AFTER_CREATE = ['status', 'invoice_id', 'approved_by'];

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'student_id', 'component_id',
        'description', 'quantity', 'unit_rate_minor', 'amount_minor', 'currency',
        'source_type', 'source_id', 'status', 'invoice_id', 'raised_by', 'approved_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AdHocChargeFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Model $model): void {
            PeriodGuard::assertWritable($model);
        });

        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $illegal = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($illegal !== []) {
                throw new InvalidStateTransitionException(
                    'An ad_hoc_charges row may only change status, invoice_id, or approved_by after creation.',
                    ['dirty' => $illegal],
                );
            }
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
     * @return BelongsTo<FeeComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }
}
