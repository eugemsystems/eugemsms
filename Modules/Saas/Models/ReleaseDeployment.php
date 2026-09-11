<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Saas\Database\Factories\ReleaseDeploymentFactory;

/**
 * Book J SAA-02 §2/BR-SAA-02-005/007.
 *
 * @property int $id
 * @property string $version
 * @property string $deployment_stage
 * @property array<int, int>|null $canary_tenant_ids
 * @property string $migration_status
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property bool $rollback_available
 */
class ReleaseDeployment extends Model
{
    use Auditable;

    /** @use HasFactory<ReleaseDeploymentFactory> */
    use HasFactory;

    protected $fillable = [
        'version', 'deployment_stage', 'canary_tenant_ids', 'migration_status', 'started_at', 'completed_at', 'rollback_available',
    ];

    protected function casts(): array
    {
        return [
            'canary_tenant_ids' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'rollback_available' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReleaseDeploymentFactory::new();
    }
}
