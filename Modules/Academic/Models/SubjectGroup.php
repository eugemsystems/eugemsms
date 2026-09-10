<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Academic\Database\Factories\SubjectGroupFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book D ACA-01 §2 ⭐/BR-ACA-01-004. The join point `FIN-02` prices
 * per-subject rates against — renaming this without realising it is the
 * key `FIN-02` prices against is the exact mistake §5's screen note
 * warns about.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $requires_laboratory
 * @property bool $requires_workshop
 * @property int|null $sort_order
 * @property bool $is_active
 */
class SubjectGroup extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SubjectGroupFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'name', 'description', 'requires_laboratory',
        'requires_workshop', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_laboratory' => 'boolean',
            'requires_workshop' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SubjectGroupFactory::new();
    }

    /**
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
