<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\DiscountScheme;
use Modules\People\Database\Factories\BursaryEndowmentFactory;

/**
 * Book K PPL-06 §2/BR-PPL-06-008 ⭐/009 — see the owning migration's
 * docblock for the `is_anonymous` addition.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $donor_name
 * @property int|null $alumnus_id
 * @property int|null $endowment_capital_minor
 * @property int|null $annual_commitment_minor
 * @property string $currency
 * @property int $funds_scheme_id
 * @property string|null $named_recognition
 * @property bool $is_anonymous
 * @property Carbon $starts_on
 * @property string $status
 */
class BursaryEndowment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BursaryEndowmentFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'donor_name', 'alumnus_id', 'endowment_capital_minor', 'annual_commitment_minor',
        'currency', 'funds_scheme_id', 'named_recognition', 'is_anonymous', 'starts_on', 'status',
    ];

    protected function casts(): array
    {
        return [
            'endowment_capital_minor' => 'integer',
            'annual_commitment_minor' => 'integer',
            'is_anonymous' => 'boolean',
            'starts_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BursaryEndowmentFactory::new();
    }

    /**
     * @return BelongsTo<Alumnus, $this>
     */
    public function alumnus(): BelongsTo
    {
        return $this->belongsTo(Alumnus::class);
    }

    /**
     * @return BelongsTo<DiscountScheme, $this>
     */
    public function fundsScheme(): BelongsTo
    {
        return $this->belongsTo(DiscountScheme::class, 'funds_scheme_id');
    }

    /**
     * @return HasMany<Donation, $this>
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'bursary_endowment_id');
    }

    public function displayName(): string
    {
        return $this->is_anonymous ? 'Anonymous' : $this->donor_name;
    }
}
