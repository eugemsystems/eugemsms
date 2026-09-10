<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Stores\Database\Factories\QuotationLineFactory;

/**
 * @property int $id
 * @property int $quotation_id
 * @property int|null $requisition_line_id
 * @property float $quantity
 * @property int $unit_price_minor
 * @property int $line_total_minor
 */
class QuotationLine extends Model
{
    /** @use HasFactory<QuotationLineFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'quotation_id', 'requisition_line_id', 'description', 'quantity', 'unit_price_minor',
        'line_total_minor', 'lead_time_days',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return QuotationLineFactory::new();
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<PurchaseRequisitionLine, $this>
     */
    public function requisitionLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisitionLine::class, 'requisition_line_id');
    }
}
