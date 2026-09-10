<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\SubjectAccessRequestFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-008 ⭐/009/010.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $request_type
 * @property string $subject_type
 * @property int|null $subject_id
 * @property string $requester_name
 * @property string|null $requester_relationship
 * @property bool $identity_verified
 * @property string|null $verification_method
 * @property int|null $verified_by
 * @property Carbon $received_at
 * @property Carbon $due_by
 * @property string $scope_description
 * @property string $status
 * @property string|null $refusal_grounds
 * @property int|null $response_file_id
 * @property Carbon|null $fulfilled_at
 * @property int|null $handled_by
 */
class SubjectAccessRequest extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SubjectAccessRequestFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'request_type', 'subject_type', 'subject_id', 'requester_name',
        'requester_relationship', 'identity_verified', 'verification_method', 'verified_by',
        'received_at', 'due_by', 'scope_description', 'status', 'refusal_grounds',
        'response_file_id', 'fulfilled_at', 'handled_by',
    ];

    protected function casts(): array
    {
        return [
            'identity_verified' => 'boolean',
            'received_at' => 'datetime',
            'due_by' => 'date',
            'fulfilled_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubjectAccessRequestFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
