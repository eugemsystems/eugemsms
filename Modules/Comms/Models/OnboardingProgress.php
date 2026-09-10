<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\OnboardingProgressFactory;

/**
 * Book I COM-03 §2/BR-COM-03-008/009.
 *
 * @property int $id
 * @property int $user_id
 * @property string $persona
 * @property array<int, string> $steps_completed
 * @property Carbon|null $completed_at
 * @property Carbon|null $skipped_at
 */
class OnboardingProgress extends Model
{
    /** @use HasFactory<OnboardingProgressFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'persona', 'steps_completed', 'completed_at', 'skipped_at',
    ];

    protected function casts(): array
    {
        return [
            'steps_completed' => 'array',
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return OnboardingProgressFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasCompletedStep(string $step): bool
    {
        return in_array($step, $this->steps_completed, true);
    }
}
