<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\PolicyFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book H3 CMP-04 §2/BR-CMP-04-001.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $title
 * @property string $category
 * @property string $version
 * @property string|null $content
 * @property int|null $document_file_id
 * @property Carbon $effective_from
 * @property Carbon|null $review_due_on
 * @property int|null $approved_by
 * @property Carbon|null $board_approved_on
 * @property bool $requires_acknowledgement
 * @property array<int, string>|null $acknowledgement_audiences
 * @property string $status
 * @property int|null $supersedes_policy_id
 */
class Policy extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PolicyFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'title', 'category', 'version', 'content', 'document_file_id',
        'effective_from', 'review_due_on', 'approved_by', 'board_approved_on', 'requires_acknowledgement',
        'acknowledgement_audiences', 'status', 'supersedes_policy_id',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'review_due_on' => 'date',
            'board_approved_on' => 'date',
            'requires_acknowledgement' => 'boolean',
            'acknowledgement_audiences' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PolicyFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function supersedesPolicy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_policy_id');
    }

    /**
     * @return HasMany<PolicyAcknowledgement, $this>
     */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(PolicyAcknowledgement::class, 'policy_id');
    }
}
