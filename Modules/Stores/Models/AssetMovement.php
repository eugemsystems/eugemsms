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
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Models\Journal;
use Modules\Stores\Database\Factories\AssetMovementFactory;

/**
 * Book H1 FIN-10 §2/BR-FIN-10-009 ⭐ — APPEND-ONLY, the same
 * model-level guard `Modules\Stores\Models\StockMovement` uses (see
 * its own docblock for why the real DB-grant REVOKE is a deployment
 * step, not a migration).
 *
 * @property int $id
 * @property int $school_id
 * @property int $asset_id
 * @property string $movement_type
 * @property string|null $from_value
 * @property string|null $to_value
 * @property string|null $reason
 * @property int|null $journal_id
 * @property int $performed_by
 * @property Carbon $occurred_at
 */
class AssetMovement extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssetMovementFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'asset_id', 'movement_type', 'from_value', 'to_value', 'reason', 'journal_id',
        'performed_by', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('asset_movements is append-only and can never be updated (BR-FIN-10-009).');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('asset_movements is append-only and can never be deleted (BR-FIN-10-009).');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssetMovementFactory::new();
    }

    /**
     * @return BelongsTo<FixedAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
