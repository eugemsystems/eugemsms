<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Stores\Database\Factories\SupplierTaxClearanceFactory;

/**
 * Book H1 FIN-08 §2/§3 ⭐ — 🇿🇼 ITF263.
 *
 * @property int $id
 * @property int $school_id
 * @property int $supplier_id
 * @property string $certificate_number
 * @property Carbon $issued_on
 * @property Carbon $expires_on
 * @property string $status
 */
class SupplierTaxClearance extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SupplierTaxClearanceFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'supplier_id', 'certificate_number', 'issued_on', 'expires_on', 'document_file_id',
        'verified_by', 'verified_at', 'verification_method', 'status',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * BR-FIN-08-003 ⭐ — validity is assessed on a given date, never
     * "today" by default, since the invoice date is what governs
     * withholding, not the date the check happens to run.
     */
    public function isValidOn(CarbonInterface $date): bool
    {
        return $this->status === 'valid'
            && $this->issued_on->lessThanOrEqualTo($date)
            && $this->expires_on->greaterThanOrEqualTo($date);
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SupplierTaxClearanceFactory::new();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
