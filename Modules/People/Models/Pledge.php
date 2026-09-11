<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Database\Factories\PledgeFactory;

/**
 * Book K PPL-06 §2/BR-PPL-06-006. A stated intention — only
 * `paid_to_date_minor` (moved exclusively by `RecordDonationAction`)
 * is ever recognised as real income.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $campaign_id
 * @property int|null $alumnus_id
 * @property string $donor_name
 * @property string $donor_type
 * @property int $pledged_amount_minor
 * @property string $currency
 * @property array<int, array<string, mixed>>|null $schedule
 * @property int $paid_to_date_minor
 * @property string|null $recognition_tier
 * @property bool $is_anonymous
 * @property string $status
 */
class Pledge extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PledgeFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'campaign_id', 'alumnus_id', 'donor_name', 'donor_type', 'pledged_amount_minor',
        'currency', 'schedule', 'paid_to_date_minor', 'recognition_tier', 'is_anonymous', 'status',
    ];

    protected function casts(): array
    {
        return [
            'pledged_amount_minor' => 'integer',
            'schedule' => 'array',
            'paid_to_date_minor' => 'integer',
            'is_anonymous' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PledgeFactory::new();
    }

    /**
     * @return BelongsTo<CapitalCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CapitalCampaign::class);
    }

    /**
     * @return BelongsTo<Alumnus, $this>
     */
    public function alumnus(): BelongsTo
    {
        return $this->belongsTo(Alumnus::class);
    }

    /**
     * @return HasMany<Donation, $this>
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }
}
