<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\LoanFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\Finance\Models\AdHocCharge;

/**
 * Book K ACA-10 §2/BR-ACA-10-002..006.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int $copy_id
 * @property string $borrower_type
 * @property int $borrower_id
 * @property string $borrower_category
 * @property Carbon $issued_on
 * @property Carbon $due_on
 * @property int $renewal_count
 * @property Carbon|null $returned_on
 * @property string|null $condition_at_return
 * @property string $status
 * @property int|null $fine_charge_id
 */
class Loan extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'term_id', 'copy_id', 'borrower_type', 'borrower_id', 'borrower_category', 'issued_on',
        'due_on', 'renewal_count', 'returned_on', 'condition_at_return', 'status', 'fine_charge_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'renewal_count' => 'integer',
            'returned_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LoanFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<LibraryCopy, $this>
     */
    public function copy(): BelongsTo
    {
        return $this->belongsTo(LibraryCopy::class, 'copy_id');
    }

    /**
     * @return BelongsTo<AdHocCharge, $this>
     */
    public function fineCharge(): BelongsTo
    {
        return $this->belongsTo(AdHocCharge::class, 'fine_charge_id');
    }

    public function isOverdue(?Carbon $asOf = null): bool
    {
        $asOf ??= Carbon::today();

        return $this->status === 'active' && $asOf->greaterThan($this->due_on);
    }
}
