<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Saas\Database\Factories\BroadcastAnnouncementFactory;

/**
 * Book J SAA-02 §2/BR-SAA-02-006/007.
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property string $severity
 * @property array<int, int>|null $target_tenant_ids
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int $posted_by
 */
class BroadcastAnnouncement extends Model
{
    use Auditable;

    /** @use HasFactory<BroadcastAnnouncementFactory> */
    use HasFactory;

    protected $fillable = [
        'title', 'body', 'severity', 'target_tenant_ids', 'starts_at', 'ends_at', 'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'target_tenant_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BroadcastAnnouncementFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function targets(int $tenantId): bool
    {
        return $this->target_tenant_ids === null || in_array($tenantId, $this->target_tenant_ids, true);
    }
}
