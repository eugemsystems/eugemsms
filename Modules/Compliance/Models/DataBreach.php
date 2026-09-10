<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\DataBreachFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-011 ⭐/012.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property Carbon $detected_at
 * @property Carbon|null $occurred_at
 * @property string $breach_type
 * @property string $description
 * @property array<int, string> $data_categories
 * @property int|null $records_affected
 * @property int|null $subjects_affected
 * @property bool $includes_minors
 * @property string $severity
 * @property string|null $containment_actions
 * @property Carbon|null $contained_at
 * @property bool $authority_notified
 * @property Carbon|null $authority_notified_at
 * @property bool $subjects_notified
 * @property Carbon|null $subjects_notified_at
 * @property string|null $root_cause
 * @property string|null $remedial_actions
 * @property string $status
 * @property int $reported_by
 */
class DataBreach extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DataBreachFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'detected_at', 'occurred_at', 'breach_type', 'description', 'data_categories',
        'records_affected', 'subjects_affected', 'includes_minors', 'severity', 'containment_actions',
        'contained_at', 'authority_notified', 'authority_notified_at', 'subjects_notified',
        'subjects_notified_at', 'root_cause', 'remedial_actions', 'status', 'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'occurred_at' => 'datetime',
            'data_categories' => 'array',
            'includes_minors' => 'boolean',
            'contained_at' => 'datetime',
            'authority_notified' => 'boolean',
            'authority_notified_at' => 'datetime',
            'subjects_notified' => 'boolean',
            'subjects_notified_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DataBreachFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
