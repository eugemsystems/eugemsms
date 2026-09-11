<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\Tenant;
use Modules\Saas\Database\Factories\TenantInvoiceFactory;

/**
 * Book J SAA-01 §2 ⭐/BR-SAA-01-001 ⭐ — the vendor's own AR, structurally
 * separate from any school's `FIN-01` ledger.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $subscription_id
 * @property string $invoice_number
 * @property string $period_month
 * @property array<int, array<string, mixed>> $line_items
 * @property int $subtotal_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property string $currency
 * @property Carbon $due_date
 * @property string $status
 */
class TenantInvoice extends Model
{
    /** @use HasFactory<TenantInvoiceFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'tenant_id', 'subscription_id', 'invoice_number', 'period_month', 'line_items',
        'subtotal_minor', 'tax_minor', 'total_minor', 'currency', 'due_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'line_items' => 'array',
            'subtotal_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'due_date' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TenantInvoiceFactory::new();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return HasMany<TenantPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(TenantPayment::class, 'invoice_id');
    }

    public function amountPaidMinor(): int
    {
        return (int) $this->payments()->sum('amount_minor');
    }
}
