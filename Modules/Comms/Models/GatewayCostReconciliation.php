<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Comms\Database\Factories\GatewayCostReconciliationFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book I COM-01 §2/BR-COM-01-012 (AC-COM-01-006).
 *
 * @property int $id
 * @property int $school_id
 * @property int $gateway_id
 * @property string $period_month
 * @property int $system_recorded_minor
 * @property int|null $provider_invoiced_minor
 * @property int|null $variance_minor
 * @property int|null $provider_statement_file_id
 * @property string $status
 * @property int|null $reconciled_by
 */
class GatewayCostReconciliation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GatewayCostReconciliationFactory> */
    use HasFactory;

    protected $table = 'gateway_cost_reconciliation';

    protected $fillable = [
        'school_id', 'gateway_id', 'period_month', 'system_recorded_minor', 'provider_invoiced_minor',
        'variance_minor', 'provider_statement_file_id', 'status', 'reconciled_by',
    ];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GatewayCostReconciliationFactory::new();
    }

    /**
     * @return BelongsTo<MessageGateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(MessageGateway::class, 'gateway_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }
}
