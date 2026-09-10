<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\OfflineSyncManifestFactory;

/**
 * Book I COM-03 §4/BR-COM-03-010.
 *
 * @property int $id
 * @property int $user_id
 * @property string $data_category
 * @property Carbon|null $last_synced_at
 * @property string|null $sync_token
 * @property int $cache_ttl_hours
 */
class OfflineSyncManifest extends Model
{
    /** @use HasFactory<OfflineSyncManifestFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'data_category', 'last_synced_at', 'sync_token', 'cache_ttl_hours',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return OfflineSyncManifestFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
