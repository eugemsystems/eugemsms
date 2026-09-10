<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Security\Database\Factories\ContractorSiteVisitFactory;

/**
 * Book H2 OPS-06 §3 ⭐/BR-OPS-06-002 — see this table's own migration
 * docblock for why it exists beyond the spec's literal schema.
 *
 * @property int $id
 * @property int $school_id
 * @property int $contractor_worker_id
 * @property Carbon $signed_in_at
 * @property int $gate_staff_in
 * @property Carbon|null $signed_out_at
 * @property int|null $gate_staff_out
 */
class ContractorSiteVisit extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ContractorSiteVisitFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'contractor_worker_id', 'signed_in_at', 'gate_staff_in', 'signed_out_at', 'gate_staff_out',
    ];

    protected function casts(): array
    {
        return [
            'signed_in_at' => 'datetime',
            'signed_out_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ContractorWorker, $this>
     */
    public function contractorWorker(): BelongsTo
    {
        return $this->belongsTo(ContractorWorker::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function gateStaffIn(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gate_staff_in');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function gateStaffOut(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gate_staff_out');
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ContractorSiteVisitFactory::new();
    }
}
