<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Intelligence\Database\Factories\ExecutiveDigestFactory;

/**
 * Book J INT-02 §2 ⭐/BR-INT-02-004/005.
 *
 * @property int $id
 * @property int $school_id
 * @property int $recipient_user_id
 * @property Carbon $digest_date
 * @property array<string, mixed> $content_summary
 * @property string $delivered_via
 * @property Carbon|null $sent_at
 */
class ExecutiveDigest extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ExecutiveDigestFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'recipient_user_id', 'digest_date', 'content_summary', 'delivered_via', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'digest_date' => 'date',
            'content_summary' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ExecutiveDigestFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function isAllGreen(): bool
    {
        return ($this->content_summary['all_green'] ?? false) === true;
    }
}
