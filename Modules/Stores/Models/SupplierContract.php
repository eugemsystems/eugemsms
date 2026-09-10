<?php

declare(strict_types=1);

namespace Modules\Stores\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;
use Modules\Stores\Database\Factories\SupplierContractFactory;

/**
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $supplier_id
 * @property string $contract_number
 * @property string $title
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property bool $auto_renew
 * @property int|null $renewal_notice_days
 * @property string $status
 */
class SupplierContract extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SupplierContractFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'supplier_id', 'contract_number', 'title', 'contract_type', 'starts_on', 'ends_on',
        'value_minor', 'currency', 'renewal_notice_days', 'auto_renew', 'document_file_id',
        'owner_staff_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'auto_renew' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SupplierContractFactory::new();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function ownerStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'owner_staff_id');
    }
}
