<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Stores\Database\Factories\QuotationRequestFactory;

/**
 * Book H1 FIN-08 §2/BR-FIN-08-007/008.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $requisition_id
 * @property string $request_number
 * @property array<int, int> $suppliers_invited
 * @property Carbon $issued_on
 * @property Carbon $closes_on
 * @property string $status
 * @property int|null $awarded_quotation_id
 * @property string|null $award_justification
 * @property int|null $awarded_by
 */
class QuotationRequest extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<QuotationRequestFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'requisition_id', 'request_number', 'suppliers_invited', 'issued_on', 'closes_on',
        'status', 'evaluation_criteria', 'awarded_quotation_id', 'award_justification', 'awarded_by',
    ];

    protected function casts(): array
    {
        return [
            'suppliers_invited' => 'array',
            'issued_on' => 'date',
            'closes_on' => 'date',
            'evaluation_criteria' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return QuotationRequestFactory::new();
    }

    /**
     * @return BelongsTo<PurchaseRequisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    /**
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}
