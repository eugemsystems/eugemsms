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
use Modules\Finance\Database\Factories\BillingRunFactory;

/**
 * Book B FIN-02 §2/§3/BR-FIN-02-013 ⭐. One pass of the billing pipeline
 * — `computing → preview → approved → committed` (or `failed`/
 * `cancelled`) are four distinct, human-gated states. See
 * `ComputeBillingRunAction`/`ApproveBillingRunAction`/`CommitBillingRunAction`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property array<string, mixed>|null $scope_filter
 * @property string $status
 * @property int $total_learners
 * @property int $computed_count
 * @property int $exception_count
 * @property int $total_gross_minor
 * @property int $total_discount_minor
 * @property int $total_net_minor
 * @property array<string, mixed>|null $currency_totals
 * @property array<string, mixed>|null $variance_report
 * @property array<string, mixed>|null $exception_report
 * @property int $computed_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $committed_at
 * @property string|null $journal_batch_uuid
 */
class BillingRun extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BillingRunFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'scope_filter', 'status',
        'total_learners', 'computed_count', 'exception_count', 'total_gross_minor',
        'total_discount_minor', 'total_net_minor', 'currency_totals', 'variance_report',
        'exception_report', 'computed_by', 'approved_by', 'approved_at', 'committed_at',
        'journal_batch_uuid',
    ];

    protected function casts(): array
    {
        return [
            'scope_filter' => 'array',
            'total_learners' => 'integer',
            'computed_count' => 'integer',
            'exception_count' => 'integer',
            'currency_totals' => 'array',
            'variance_report' => 'array',
            'exception_report' => 'array',
            'approved_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BillingRunFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function computedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'computed_by');
    }
}
