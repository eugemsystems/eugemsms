<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\Install\DeploymentMode;

/**
 * Book A CORE-01 §2. One row, ever — records the platform's own
 * installation, not tenant data. `installation_uuid` is generated once
 * and never regenerated (BR-CORE-01-012); it is the licence identity.
 *
 * @property int $id
 * @property Carbon $installed_at
 * @property string $installed_version
 * @property DeploymentMode $deployment_mode
 * @property string|null $licence_key
 * @property Carbon|null $licence_activated_at
 * @property Carbon|null $licence_expires_at
 * @property string $installation_uuid
 * @property string|null $server_fingerprint
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SystemInstallation extends Model
{
    protected $fillable = [
        'installed_at',
        'installed_version',
        'deployment_mode',
        'licence_key',
        'licence_activated_at',
        'licence_expires_at',
        'installation_uuid',
        'server_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
            'deployment_mode' => DeploymentMode::class,
            'licence_activated_at' => 'datetime',
            'licence_expires_at' => 'datetime',
        ];
    }

    public function isInLicenceGracePeriod(): bool
    {
        return $this->licence_activated_at === null;
    }

    public function isLicenceGraceExpired(): bool
    {
        return $this->licence_expires_at !== null && $this->licence_expires_at->isPast();
    }
}
