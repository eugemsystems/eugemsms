<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Security\Database\Factories\ContractorFactory;
use Modules\Stores\Models\Supplier;

/**
 * Book H2 OPS-06 §2/BR-OPS-06-001.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $supplier_id
 * @property string $company_name
 * @property string|null $contact_person
 * @property string|null $phone
 * @property string|null $work_type
 * @property Carbon|null $insurance_expires_on
 * @property int|null $insurance_file_id
 * @property Carbon|null $safety_induction_on
 * @property Carbon|null $induction_valid_until
 * @property Carbon|null $police_clearance_on
 * @property string $status
 * @property int|null $approved_by
 */
class Contractor extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ContractorFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'supplier_id', 'company_name', 'contact_person', 'phone', 'work_type',
        'insurance_expires_on', 'insurance_file_id', 'safety_induction_on', 'induction_valid_until',
        'police_clearance_on', 'status', 'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'insurance_expires_on' => 'date',
            'safety_induction_on' => 'date',
            'induction_valid_until' => 'date',
            'police_clearance_on' => 'date',
        ];
    }

    public function hasSiteAccessRequirements(): bool
    {
        $today = now()->startOfDay();

        if ($this->status !== 'approved') {
            return false;
        }

        if ($this->insurance_expires_on === null || $this->insurance_expires_on->lessThan($today)) {
            return false;
        }

        if ($this->induction_valid_until === null || $this->induction_valid_until->lessThan($today)) {
            return false;
        }

        return true;
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ContractorFactory::new();
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<ContractorWorker, $this>
     */
    public function workers(): HasMany
    {
        return $this->hasMany(ContractorWorker::class);
    }
}
