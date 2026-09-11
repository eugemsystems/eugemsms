<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Database\Factories\TenantPaymentFactory;

/**
 * Book J SAA-01 §2/BR-SAA-01-009 — `gateway_reference` cross-references
 * FIN-05's own driver output; this table never runs a second gateway
 * integration.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $invoice_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $payment_method
 * @property string|null $gateway_reference
 * @property Carbon $received_at
 */
class TenantPayment extends Model
{
    /** @use HasFactory<TenantPaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'invoice_id', 'amount_minor', 'currency', 'payment_method', 'gateway_reference', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TenantPaymentFactory::new();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<TenantInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TenantInvoice::class, 'invoice_id');
    }
}
