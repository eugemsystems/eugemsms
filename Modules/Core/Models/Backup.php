<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\BackupFactory;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book A CORE-13 §2. Not `BelongsToSchool` — most backups are system-
 * scoped; a `school_export` backup carries its school in `scope_id`
 * instead, same reasoning as CORE-11/12's infrastructure tables.
 *
 * @property int $id
 * @property string $ulid
 * @property string $type
 * @property string $scope
 * @property int|null $scope_id
 * @property string $disk
 * @property string $path
 * @property int $size_bytes
 * @property string|null $checksum
 * @property bool $is_encrypted
 * @property string $status
 * @property Carbon|null $verified_at
 * @property string|null $verification_notes
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property string $triggered_by
 * @property int|null $created_by
 */
class Backup extends Model
{
    /** @use HasFactory<BackupFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'type', 'scope', 'scope_id', 'disk', 'path', 'size_bytes', 'checksum',
        'is_encrypted', 'status', 'verified_at', 'verification_notes', 'started_at',
        'completed_at', 'expires_at', 'triggered_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
            'verified_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BackupFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<RestoreTest, $this>
     */
    public function restoreTests(): HasMany
    {
        return $this->hasMany(RestoreTest::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
