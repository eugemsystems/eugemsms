<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Reporting\Database\Factories\CloseCheckAcknowledgementFactory;

/**
 * Book H3 FIN-12 §4/BR-FIN-12-010.
 *
 * @property int $id
 * @property int $school_id
 * @property int $checklist_id
 * @property string $check_key
 * @property string $reason
 * @property int $acknowledged_by
 * @property Carbon $acknowledged_at
 * @property int|null $approved_by
 */
class CloseCheckAcknowledgement extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CloseCheckAcknowledgementFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'checklist_id', 'check_key', 'reason', 'acknowledged_by', 'acknowledged_at', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CloseCheckAcknowledgementFactory::new();
    }

    /**
     * @return BelongsTo<PeriodCloseChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(PeriodCloseChecklist::class, 'checklist_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
