<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\ContentDownloadCacheFactory;

/**
 * Book K ACA-08 §2/§3 — what's queued for offline on a device. Not
 * `BelongsToSchool`; see the owning migration's docblock.
 *
 * @property int $id
 * @property int $user_id
 * @property int $content_item_id
 * @property Carbon|null $downloaded_at
 * @property string|null $device_id
 */
class ContentDownloadCache extends Model
{
    /** @use HasFactory<ContentDownloadCacheFactory> */
    use HasFactory;

    protected $table = 'content_download_cache';

    protected $fillable = ['user_id', 'content_item_id', 'downloaded_at', 'device_id'];

    protected function casts(): array
    {
        return [
            'downloaded_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ContentDownloadCacheFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ContentItem, $this>
     */
    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }
}
