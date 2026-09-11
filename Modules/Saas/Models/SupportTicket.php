<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Database\Factories\SupportTicketFactory;

/**
 * Book J SAA-03 §2 ⭐/BR-SAA-03-003 ⭐ — vendor-facing, distinct from
 * `Modules\Comms\Models\Complaint` (`COM-08`). Not `BelongsToSchool`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int|null $school_id
 * @property int $raised_by_user_id
 * @property string $subject
 * @property string $description
 * @property string $category
 * @property string $priority
 * @property Carbon $sla_due_at
 * @property int|null $assigned_vendor_staff_id
 * @property string $status
 */
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'tenant_id', 'school_id', 'raised_by_user_id', 'subject', 'description', 'category',
        'priority', 'sla_due_at', 'assigned_vendor_staff_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SupportTicketFactory::new();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedVendorStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_vendor_staff_id');
    }

    public function isBreached(): bool
    {
        return $this->sla_due_at->isPast() && ! in_array($this->status, ['resolved', 'closed'], true);
    }
}
