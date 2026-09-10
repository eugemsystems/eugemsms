<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\ContractFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;

/**
 * Book H3 CMP-04 §2 (added — see the module migration's own docblock).
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $counterparty_name
 * @property string $contract_type
 * @property string|null $description
 * @property Carbon $starts_on
 * @property Carbon|null $expires_on
 * @property int $renewal_lead_days
 * @property int|null $document_file_id
 * @property int|null $responsible_staff_id
 * @property string $status
 */
class Contract extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'counterparty_name', 'contract_type', 'description', 'starts_on', 'expires_on',
        'renewal_lead_days', 'document_file_id', 'responsible_staff_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ContractFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }
}
