<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\RestoreTestFactory;

/**
 * Book A CORE-13 §2 ⭐ (BR-CORE-13-003) — extended with `requested_by`/
 * `approved_by` for BR-CORE-13-009's dual-authorised production
 * restores; see the spec's implementation note at CORE-13 §2.
 *
 * @property int $id
 * @property int $backup_id
 * @property string $status
 * @property string $target_environment
 * @property array<string, mixed>|null $checks_performed
 * @property int|null $duration_seconds
 * @property string|null $error
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $tested_at
 */
class RestoreTest extends Model
{
    /** @use HasFactory<RestoreTestFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'backup_id', 'status', 'target_environment', 'checks_performed', 'duration_seconds',
        'error', 'requested_by', 'approved_by', 'tested_at',
    ];

    protected function casts(): array
    {
        return [
            'checks_performed' => 'array',
            'tested_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return RestoreTestFactory::new();
    }

    /**
     * @return BelongsTo<Backup, $this>
     */
    public function backup(): BelongsTo
    {
        return $this->belongsTo(Backup::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function passed(): bool
    {
        return $this->status === 'passed';
    }
}
