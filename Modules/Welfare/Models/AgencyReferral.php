<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Casts\SecondaryEncrypted;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Welfare\Database\Factories\AgencyReferralFactory;

/**
 * Book G BRD-08 §2/BR-BRD-08-014.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $case_id
 * @property string $agency_type
 * @property string $agency_name
 * @property string|null $contact_person
 * @property Carbon $referred_at
 * @property int $referred_by
 * @property string $reason
 * @property string|null $information_shared
 * @property string $consent_basis
 * @property Carbon|null $acknowledgement_at
 * @property string|null $agency_reference
 * @property string|null $outcome
 * @property string $status
 */
class AgencyReferral extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AgencyReferralFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'case_id', 'agency_type', 'agency_name', 'contact_person', 'referred_at', 'referred_by',
        'reason', 'information_shared', 'consent_basis', 'acknowledgement_at', 'agency_reference', 'outcome', 'status',
    ];

    protected function casts(): array
    {
        return [
            'referred_at' => 'datetime',
            'reason' => SecondaryEncrypted::class,
            'information_shared' => SecondaryEncrypted::class,
            'acknowledgement_at' => 'datetime',
            'outcome' => SecondaryEncrypted::class,
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AgencyReferralFactory::new();
    }

    /**
     * @return BelongsTo<SafeguardingCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(SafeguardingCase::class, 'case_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }
}
