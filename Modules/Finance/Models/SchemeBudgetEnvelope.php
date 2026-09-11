<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Finance\Database\Factories\SchemeBudgetEnvelopeFactory;

/**
 * Book K FIN-07 §2/BR-FIN-07-009 ⭐. `budget_minor` null = uncapped.
 * `committed_minor` tracks discount that has been computed into a
 * `draft`/`preview` billing run but not yet invoiced; `utilised_minor`
 * tracks what's actually been posted to the GL — the same
 * committed-vs-utilised split any budgeting system needs so a
 * still-editable draft doesn't permanently consume the cap.
 *
 * @property int $id
 * @property int $school_id
 * @property int $scheme_id
 * @property int $academic_year_id
 * @property int|null $budget_minor
 * @property string|null $currency
 * @property int $committed_minor
 * @property int $utilised_minor
 */
class SchemeBudgetEnvelope extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SchemeBudgetEnvelopeFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'scheme_id', 'academic_year_id', 'budget_minor', 'currency', 'committed_minor', 'utilised_minor',
    ];

    protected function casts(): array
    {
        return [
            'budget_minor' => 'integer',
            'committed_minor' => 'integer',
            'utilised_minor' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SchemeBudgetEnvelopeFactory::new();
    }

    /**
     * @return BelongsTo<DiscountScheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(DiscountScheme::class, 'scheme_id');
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function remainingMinor(): ?int
    {
        if ($this->budget_minor === null) {
            return null;
        }

        return $this->budget_minor - $this->committed_minor - $this->utilised_minor;
    }

    public function wouldExceed(int $additionalMinor): bool
    {
        $remaining = $this->remainingMinor();

        return $remaining !== null && $additionalMinor > $remaining;
    }
}
