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
use Modules\Finance\Models\Journal;
use Modules\Stores\Database\Factories\AssetDisposalFactory;

/**
 * Book H1 FIN-10 §2/BR-FIN-10-013.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $asset_id
 * @property Carbon $disposal_date
 * @property string $disposal_method
 * @property int $proceeds_minor
 * @property string $currency
 * @property int $nbv_at_disposal_minor
 * @property int $gain_loss_minor
 * @property string $reason
 * @property int|null $approved_by
 * @property int|null $journal_id
 */
class AssetDisposal extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssetDisposalFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'asset_id', 'disposal_date', 'disposal_method', 'proceeds_minor', 'currency',
        'nbv_at_disposal_minor', 'gain_loss_minor', 'buyer', 'reason', 'approval_request_id', 'approved_by',
        'journal_id', 'document_file_id',
    ];

    protected function casts(): array
    {
        return [
            'disposal_date' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssetDisposalFactory::new();
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
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
