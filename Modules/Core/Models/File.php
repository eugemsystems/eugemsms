<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\FileFactory;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-10 §2. Not `BelongsToSchool` — every write supplies
 * `school_id` explicitly, same reasoning as CORE-08/09's tables.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property string $hash
 * @property string $category
 * @property string|null $attachable_type
 * @property int|null $attachable_id
 * @property bool $is_sensitive
 * @property string $scan_status
 * @property string|null $scan_result
 * @property array<string, string>|null $variants
 * @property Carbon|null $expires_on
 * @property int $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    use HasUlid;
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'disk', 'path', 'original_name', 'mime_type', 'extension',
        'size_bytes', 'hash', 'category', 'attachable_type', 'attachable_id',
        'is_sensitive', 'scan_status', 'scan_result', 'variants', 'expires_on',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
            'variants' => 'array',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FileFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isScanPending(): bool
    {
        return $this->scan_status === 'pending';
    }

    public function hasCleanScan(): bool
    {
        return in_array($this->scan_status, ['clean', 'skipped'], true);
    }

    public function isInfected(): bool
    {
        return $this->scan_status === 'infected';
    }

    public function isDownloadableBy(int $userId): bool
    {
        if ($this->isInfected()) {
            return false;
        }

        if ($this->isScanPending()) {
            return $this->uploaded_by === $userId;
        }

        return true;
    }
}
