<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Operations\Models\WorkOrder;
use Modules\Transport\Database\Factories\VehicleComplianceFactory;

/**
 * Book H2 OPS-01 §2 🇿🇼/BR-OPS-01-001/002 ⭐.
 *
 * @property int $id
 * @property int $school_id
 * @property int $vehicle_id
 * @property string $compliance_type
 * @property string|null $reference_number
 * @property Carbon|null $issued_on
 * @property Carbon $expires_on
 * @property int|null $cost_minor
 * @property string|null $currency
 * @property string|null $issuing_authority
 * @property string $status
 * @property int|null $renewal_wo_id
 */
class VehicleCompliance extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<VehicleComplianceFactory> */
    use HasFactory;

    protected $table = 'vehicle_compliance';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'vehicle_id', 'compliance_type', 'reference_number', 'issued_on', 'expires_on',
        'cost_minor', 'currency', 'document_file_id', 'issuing_authority', 'status', 'renewal_wo_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return VehicleComplianceFactory::new();
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function renewalWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'renewal_wo_id');
    }
}
