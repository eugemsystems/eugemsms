<?php

declare(strict_types=1);

namespace Modules\Fiscal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Fiscal\Database\Factories\FiscalDayFactory;

/**
 * Book H3 FIN-13 §5/BR-FIN-13-006/007/008.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $device_id
 * @property int $fiscal_day_number
 * @property Carbon $opened_at
 * @property int $opened_by
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string $local_status
 * @property string|null $fdms_status
 * @property int $receipt_count
 * @property array<string, mixed>|null $counters
 * @property string|null $day_hash
 * @property string|null $day_signature
 * @property int $close_attempts
 * @property string|null $close_error
 */
class FiscalDay extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FiscalDayFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'device_id', 'fiscal_day_number', 'opened_at', 'opened_by', 'closed_at', 'closed_by',
        'local_status', 'fdms_status', 'receipt_count', 'counters', 'day_hash', 'day_signature',
        'close_attempts', 'close_error',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'counters' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FiscalDayFactory::new();
    }

    /**
     * @return BelongsTo<FiscalDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(FiscalDevice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}
