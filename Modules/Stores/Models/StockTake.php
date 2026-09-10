<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Journal;
use Modules\Stores\Database\Factories\StockTakeFactory;

/**
 * Book H1 FIN-09 §2/§7/BR-FIN-09-014 ⭐ — blind by default.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $store_id
 * @property string $take_number
 * @property string $take_type
 * @property Carbon $scheduled_for
 * @property Carbon|null $counted_on
 * @property string $status
 * @property bool $is_blind_count
 * @property int|null $total_variance_minor
 * @property int $line_count
 * @property int $variance_line_count
 * @property int|null $counted_by
 * @property int|null $verified_by
 * @property int|null $approved_by
 * @property int|null $approval_request_id
 * @property int|null $journal_id
 */
class StockTake extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StockTakeFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'term_id', 'store_id', 'take_number', 'take_type', 'scheduled_for', 'counted_on',
        'status', 'is_blind_count', 'total_variance_minor', 'line_count', 'variance_line_count',
        'counted_by', 'verified_by', 'approved_by', 'approval_request_id', 'journal_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'counted_on' => 'date',
            'is_blind_count' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StockTakeFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return HasMany<StockTakeLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTakeLine::class, 'stock_take_id');
    }
}
