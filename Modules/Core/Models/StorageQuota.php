<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Factories\StorageQuotaFactory;

/**
 * Book A CORE-10 §2/BR-CORE-10-010.
 *
 * @property int $id
 * @property int $school_id
 * @property int $quota_bytes
 * @property int $used_bytes
 * @property int $warn_at_percent
 */
class StorageQuota extends Model
{
    /** @use HasFactory<StorageQuotaFactory> */
    use HasFactory;

    const CREATED_AT = null;

    protected $fillable = ['school_id', 'quota_bytes', 'used_bytes', 'warn_at_percent'];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StorageQuotaFactory::new();
    }

    public function hasRoomFor(int $bytes): bool
    {
        return ($this->used_bytes + $bytes) <= $this->quota_bytes;
    }

    public function percentUsed(): float
    {
        return $this->quota_bytes > 0 ? ($this->used_bytes / $this->quota_bytes) * 100 : 0.0;
    }
}
