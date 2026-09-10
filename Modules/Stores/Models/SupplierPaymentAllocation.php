<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\SupplierPaymentAllocationFactory;

/**
 * @property int $id
 * @property int $school_id
 * @property int $payment_id
 * @property int $invoice_id
 * @property int $amount_minor
 * @property string $currency
 */
class SupplierPaymentAllocation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SupplierPaymentAllocationFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'payment_id', 'invoice_id', 'amount_minor', 'currency', 'allocated_at', 'allocated_by',
    ];

    protected function casts(): array
    {
        return [
            'allocated_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SupplierPaymentAllocationFactory::new();
    }

    /**
     * @return BelongsTo<SupplierPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(SupplierPayment::class, 'payment_id');
    }

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'invoice_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }
}
