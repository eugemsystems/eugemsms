<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Academic\Database\Factories\CurriculumFrameworkFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book D ACA-01 §2. A versioned curriculum framework — 'HBC_2024',
 * 'CAMBRIDGE'. BR-ACA-01-002/003: superseding a framework never alters
 * records already created under it.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $authority
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $status
 * @property string $continuous_assessment_model
 * @property string|null $reference_circular
 * @property string|null $notes
 */
class CurriculumFramework extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CurriculumFrameworkFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'name', 'authority', 'effective_from', 'effective_to',
        'status', 'continuous_assessment_model', 'reference_circular', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CurriculumFrameworkFactory::new();
    }

    /**
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'framework_id');
    }
}
