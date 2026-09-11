<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Saas\Database\Factories\TourCompletionFactory;

/**
 * Book J SAA-03 §2/BR-SAA-03-005.
 *
 * @property int $id
 * @property int $user_id
 * @property string $tour_key
 * @property Carbon|null $completed_at
 * @property Carbon|null $skipped_at
 */
class TourCompletion extends Model
{
    /** @use HasFactory<TourCompletionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'tour_key', 'completed_at', 'skipped_at',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return TourCompletionFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ProductTour, $this>
     */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(ProductTour::class, 'tour_key', 'key');
    }
}
