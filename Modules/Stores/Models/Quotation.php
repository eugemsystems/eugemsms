<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Stores\Database\Factories\QuotationFactory;

/**
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $quotation_request_id
 * @property int $supplier_id
 * @property int $total_minor
 * @property string $currency
 * @property bool $is_compliant
 * @property string $status
 */
class Quotation extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'quotation_request_id', 'supplier_id', 'quotation_reference', 'received_on',
        'valid_until', 'subtotal_minor', 'tax_minor', 'total_minor', 'currency', 'delivery_days',
        'payment_terms_days', 'document_file_id', 'evaluation_score', 'is_compliant', 'non_compliance_note',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'valid_until' => 'date',
            'evaluation_score' => 'decimal:2',
            'is_compliant' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return QuotationFactory::new();
    }

    /**
     * @return BelongsTo<QuotationRequest, $this>
     */
    public function quotationRequest(): BelongsTo
    {
        return $this->belongsTo(QuotationRequest::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<QuotationLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class);
    }
}
