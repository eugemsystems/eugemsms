<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\ComplaintFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-003/006. See the owning migration's
 * docblock for `safeguarding_concern_id`.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $complaint_number
 * @property int $category_id
 * @property string $raised_by_type
 * @property int|null $raised_by_id
 * @property string $subject
 * @property string $description
 * @property int|null $related_student_id
 * @property string $severity
 * @property int|null $assigned_to_staff_id
 * @property Carbon $sla_due_at
 * @property string $status
 * @property string|null $resolution
 * @property int|null $satisfaction_rating
 * @property Carbon|null $closed_at
 * @property int|null $safeguarding_concern_id
 */
class Complaint extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ComplaintFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'complaint_number', 'category_id', 'raised_by_type', 'raised_by_id',
        'subject', 'description', 'related_student_id', 'severity', 'assigned_to_staff_id',
        'sla_due_at', 'status', 'resolution', 'satisfaction_rating', 'closed_at', 'safeguarding_concern_id',
    ];

    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ComplaintFactory::new();
    }

    /**
     * @return BelongsTo<ComplaintCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_to_staff_id');
    }

    /**
     * @return HasMany<ComplaintUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(ComplaintUpdate::class);
    }

    public function isRoutedToSafeguarding(): bool
    {
        return $this->safeguarding_concern_id !== null;
    }

    public function isBreached(): bool
    {
        return $this->sla_due_at->isPast() && ! in_array($this->status, ['resolved', 'closed'], true);
    }
}
