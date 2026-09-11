<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Models\Tenant;
use Modules\Saas\Database\Factories\TenantHealthSnapshotFactory;

/**
 * Book J SAA-02 §2/BR-SAA-02-003.
 *
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $snapshot_date
 * @property int $active_schools
 * @property int $active_learners
 * @property string $subscription_status
 * @property int|null $last_login_days_ago
 * @property float|null $module_adoption_percent
 * @property int $open_support_tickets
 * @property int $integrity_check_failures
 * @property float|null $health_score
 */
class TenantHealthSnapshot extends Model
{
    /** @use HasFactory<TenantHealthSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'snapshot_date', 'active_schools', 'active_learners', 'subscription_status',
        'last_login_days_ago', 'module_adoption_percent', 'open_support_tickets',
        'integrity_check_failures', 'health_score',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'active_schools' => 'integer',
            'active_learners' => 'integer',
            'last_login_days_ago' => 'integer',
            'module_adoption_percent' => 'float',
            'open_support_tickets' => 'integer',
            'integrity_check_failures' => 'integer',
            'health_score' => 'float',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TenantHealthSnapshotFactory::new();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
