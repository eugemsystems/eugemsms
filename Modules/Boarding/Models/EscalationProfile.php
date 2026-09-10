<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Boarding\Database\Factories\EscalationProfileFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book F BRD-02 §2/§3 ⭐.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_default
 */
class EscalationProfile extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EscalationProfileFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'name', 'description', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EscalationProfileFactory::new();
    }

    /**
     * @return HasMany<EscalationStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(EscalationStep::class, 'profile_id')->orderBy('step_number');
    }
}
