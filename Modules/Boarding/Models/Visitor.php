<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\VisitorFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Guardian;

/**
 * Book F BRD-03 §2/BR-BRD-03-017/018/019. `id_number` is encrypted at
 * rest.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $full_name
 * @property string|null $id_type
 * @property string|null $id_number
 * @property string|null $phone
 * @property int|null $photo_file_id
 * @property string|null $organisation
 * @property bool $is_blacklisted
 * @property string|null $blacklist_reason
 * @property int|null $blacklisted_by
 * @property bool $is_watchlisted
 * @property string|null $watchlist_note
 * @property int|null $linked_guardian_id
 */
class Visitor extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<VisitorFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'full_name', 'id_type', 'id_number', 'phone', 'photo_file_id', 'organisation',
        'is_blacklisted', 'blacklist_reason', 'blacklisted_by', 'is_watchlisted', 'watchlist_note',
        'linked_guardian_id',
    ];

    protected function casts(): array
    {
        return [
            'id_number' => 'encrypted',
            'is_blacklisted' => 'boolean',
            'is_watchlisted' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return VisitorFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function blacklistedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blacklisted_by');
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function linkedGuardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'linked_guardian_id');
    }
}
