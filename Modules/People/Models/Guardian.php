<?php

declare(strict_types=1);

namespace Modules\People\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Database\Factories\GuardianFactory;

/**
 * Book C PPL-03 §2/§3. An independent record — never a nullable column
 * on `Student` — because a learner's real payer/collector/contact
 * relationships routinely don't map onto "mother" and "father".
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int|null $user_id
 * @property string $guardian_type
 * @property string|null $title
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $organisation_name
 * @property string|null $organisation_type
 * @property string|null $primary_phone
 * @property string|null $email
 * @property string $country
 * @property string $preferred_language
 * @property string $preferred_channel
 * @property string $status
 */
class Guardian extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GuardianFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'user_id', 'guardian_type', 'title', 'first_name', 'last_name',
        'organisation_name', 'organisation_type', 'primary_phone', 'email', 'country',
        'preferred_language', 'preferred_channel', 'status', 'created_by', 'updated_by',
    ];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GuardianFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<StudentGuardian, $this>
     */
    public function studentGuardians(): HasMany
    {
        return $this->hasMany(StudentGuardian::class);
    }

    public function displayName(): string
    {
        if ($this->guardian_type === 'organisation') {
            return (string) $this->organisation_name;
        }

        return trim("{$this->first_name} {$this->last_name}");
    }
}
