<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Saas\Database\Factories\TrainingCompletionFactory;

/**
 * Book J SAA-03 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property string $material_key
 * @property Carbon|null $completed_at
 */
class TrainingCompletion extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<TrainingCompletionFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'user_id', 'material_key', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TrainingCompletionFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
