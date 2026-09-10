<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\SurveyFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-001.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $title
 * @property string $purpose
 * @property string $audience_scope
 * @property bool $is_anonymous
 * @property Carbon|null $opens_at
 * @property Carbon|null $closes_at
 * @property string $status
 * @property int $response_count
 */
class Survey extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SurveyFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'title', 'purpose', 'audience_scope', 'is_anonymous',
        'opens_at', 'closes_at', 'status', 'response_count',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SurveyFactory::new();
    }

    /**
     * @return HasMany<SurveyQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<SurveyResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
