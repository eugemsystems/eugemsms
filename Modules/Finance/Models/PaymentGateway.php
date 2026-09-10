<?php

declare(strict_types=1);

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Finance\Database\Factories\PaymentGatewayFactory;

/**
 * Book B FIN-05 §3/BR-FIN-05-016/019. `credentials` is encrypted at
 * rest and permanently hidden from array/JSON serialisation — no
 * Action or API response ever returns it (AC-FIN-05-009).
 *
 * @property int $id
 * @property int $school_id
 * @property string $driver
 * @property string $name
 * @property string $credentials
 * @property array<int, string> $supported_methods
 * @property array<int, string> $supported_currencies
 * @property int $settlement_account_id
 * @property int $fee_account_id
 * @property array<string, mixed>|null $fee_model
 * @property bool $is_default
 * @property bool $is_sandbox
 * @property bool $is_active
 * @property int $priority
 * @property string|null $health_status
 */
class PaymentGateway extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PaymentGatewayFactory> */
    use HasFactory;

    protected $hidden = ['credentials'];

    protected $fillable = [
        'school_id', 'driver', 'name', 'credentials', 'supported_methods',
        'supported_currencies', 'settlement_account_id', 'fee_account_id', 'fee_model',
        'is_default', 'is_sandbox', 'is_active', 'priority', 'last_health_check_at',
        'health_status',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted',
            'supported_methods' => 'array',
            'supported_currencies' => 'array',
            'fee_model' => 'array',
            'is_default' => 'boolean',
            'is_sandbox' => 'boolean',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'last_health_check_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PaymentGatewayFactory::new();
    }

    /**
     * BR-FIN-05-017: a gateway marked `down` is hidden from the parent
     * portal.
     */
    public function isAvailable(): bool
    {
        return $this->is_active && $this->health_status !== 'down';
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'settlement_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function feeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'fee_account_id');
    }
}
