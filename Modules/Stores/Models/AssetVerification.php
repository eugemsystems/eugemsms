<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Stores\Database\Factories\AssetVerificationFactory;

/**
 * Book H1 FIN-10 §2/BR-FIN-10-011/012 ⭐.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $verification_round
 * @property int $asset_id
 * @property Carbon|null $verified_on
 * @property bool|null $found
 * @property bool|null $location_confirmed
 * @property string|null $condition_observed
 * @property int|null $verified_by
 * @property string|null $discrepancy_note
 * @property string $status
 */
class AssetVerification extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssetVerificationFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'verification_round', 'asset_id', 'verified_on', 'found', 'location_confirmed',
        'actual_location', 'condition_observed', 'scan_method', 'photo_file_id', 'verified_by',
        'discrepancy_note', 'status',
    ];

    protected function casts(): array
    {
        return [
            'verified_on' => 'date',
            'found' => 'boolean',
            'location_confirmed' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssetVerificationFactory::new();
    }

    /**
     * @return BelongsTo<FixedAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
