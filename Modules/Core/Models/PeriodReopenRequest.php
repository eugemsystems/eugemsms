<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\PeriodReopenRequestFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Support\PeriodType;

/**
 * Book A CORE-03 §3/BR-CORE-03-010 — see the owning migration's note on
 * why this exists ahead of CORE-07.
 *
 * @property int $id
 * @property int $school_id
 * @property int $term_id
 * @property PeriodType $period_type
 * @property string $reason
 * @property int $requested_by
 * @property Carbon $requested_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Term $term
 * @property-read User $requester
 * @property-read User|null $approver
 */
class PeriodReopenRequest extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PeriodReopenRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id',
        'term_id',
        'period_type',
        'reason',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_type' => PeriodType::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PeriodReopenRequestFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
