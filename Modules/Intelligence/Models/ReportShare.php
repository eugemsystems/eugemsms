<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Intelligence\Database\Factories\ReportShareFactory;

/**
 * Book J INT-01 §2 ⭐/BR-INT-01-005.
 *
 * @property int $id
 * @property int $school_id
 * @property int $report_id
 * @property string $shared_with_type
 * @property int $shared_with_id
 * @property bool $can_edit
 * @property int $shared_by
 */
class ReportShare extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReportShareFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'report_id', 'shared_with_type', 'shared_with_id', 'can_edit', 'shared_by',
    ];

    protected function casts(): array
    {
        return [
            'can_edit' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReportShareFactory::new();
    }

    /**
     * @return BelongsTo<CustomReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(CustomReport::class, 'report_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function isSharedWithUser(int $userId): bool
    {
        return $this->shared_with_type === 'user' && $this->shared_with_id === $userId;
    }
}
