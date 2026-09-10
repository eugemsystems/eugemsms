<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Intelligence\Database\Factories\SsoProvisioningConfigFactory;

/**
 * Book J INT-04 §2/BR-INT-04-006.
 *
 * @property int $id
 * @property int $school_id
 * @property string $provider
 * @property string $domain
 * @property string $credentials
 * @property bool $auto_provision_staff
 * @property string $sync_status
 * @property Carbon|null $last_synced_at
 */
class SsoProvisioningConfig extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SsoProvisioningConfigFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'provider', 'domain', 'credentials', 'auto_provision_staff', 'sync_status', 'last_synced_at',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted',
            'auto_provision_staff' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SsoProvisioningConfigFactory::new();
    }
}
