<?php

declare(strict_types=1);

namespace Modules\Welfare\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Welfare\Database\Factories\ControlledStockLogEntryFactory;

/**
 * Book G BRD-06 §2/BR-BRD-06-013 ⭐ — APPEND-ONLY, two-person. See
 * `Modules\Core\Models\FinancialAuditLogEntry` for why this is a
 * model-level guard rather than a DB grant REVOKE in this pass.
 *
 * @property int $id
 * @property int $school_id
 * @property int $clinic_stock_id
 * @property string $action
 * @property float $quantity
 * @property float $balance_after
 * @property int|null $administration_id
 * @property int $performed_by
 * @property int $witnessed_by
 * @property Carbon $occurred_at
 * @property string|null $notes
 */
class ControlledStockLogEntry extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ControlledStockLogEntryFactory> */
    use HasFactory;

    protected $table = 'controlled_stock_log';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'clinic_stock_id', 'action', 'quantity', 'balance_after', 'administration_id',
        'performed_by', 'witnessed_by', 'occurred_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new InvalidStateTransitionException('controlled_stock_log is append-only and can never be updated.');
        });

        static::deleting(function (): never {
            throw new InvalidStateTransitionException('controlled_stock_log is append-only and can never be deleted.');
        });
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ControlledStockLogEntryFactory::new();
    }

    /**
     * @return BelongsTo<ClinicStock, $this>
     */
    public function clinicStock(): BelongsTo
    {
        return $this->belongsTo(ClinicStock::class, 'clinic_stock_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function witnessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witnessed_by');
    }
}
