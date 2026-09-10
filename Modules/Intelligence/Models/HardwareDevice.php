<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Intelligence\Database\Factories\HardwareDeviceFactory;

/**
 * Book J INT-04 §2/§3 ⭐/BR-INT-04-007/008/009.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $device_type
 * @property string|null $location
 * @property string|null $purpose
 * @property int $api_client_id
 * @property string|null $firmware_version
 * @property Carbon|null $last_heartbeat_at
 * @property string $status
 */
class HardwareDevice extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<HardwareDeviceFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'device_type', 'location', 'purpose', 'api_client_id',
        'firmware_version', 'last_heartbeat_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return HardwareDeviceFactory::new();
    }

    /**
     * @return BelongsTo<ApiClient, $this>
     */
    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }
}
