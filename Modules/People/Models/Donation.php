<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Journal;
use Modules\People\Database\Factories\DonationFactory;

/**
 * Book K PPL-06 §2/BR-PPL-06-007/008 ⭐ — see the owning migration's
 * docblock for the `bursary_endowment_id` addition and the omitted
 * `receipt_id`/`FIN-04` reference.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property int|null $pledge_id
 * @property int|null $bursary_endowment_id
 * @property string $donor_name
 * @property int $amount_minor
 * @property string $currency
 * @property Carbon $received_at
 * @property int|null $journal_id
 * @property bool $is_restricted
 * @property string|null $restriction_purpose
 */
class Donation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DonationFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'term_id', 'pledge_id', 'bursary_endowment_id', 'donor_name', 'amount_minor', 'currency',
        'received_at', 'journal_id', 'is_restricted', 'restriction_purpose',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'received_at' => 'datetime',
            'is_restricted' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DonationFactory::new();
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<Pledge, $this>
     */
    public function pledge(): BelongsTo
    {
        return $this->belongsTo(Pledge::class);
    }

    /**
     * @return BelongsTo<BursaryEndowment, $this>
     */
    public function bursaryEndowment(): BelongsTo
    {
        return $this->belongsTo(BursaryEndowment::class);
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
