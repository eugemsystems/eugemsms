<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\DutyRosterFactory;

/**
 * Book C PPL-04 §2/BR-PPL-04-015.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $term_id
 * @property string $duty_type
 * @property string $name
 * @property string $rotation_pattern
 * @property array<int, string>|null $eligible_categories
 * @property bool $is_active
 */
class DutyRoster extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DutyRosterFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'academic_year_id', 'term_id', 'duty_type', 'name', 'rotation_pattern',
        'eligible_categories', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'eligible_categories' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DutyRosterFactory::new();
    }

    /**
     * @return HasMany<DutyAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(DutyAssignment::class, 'roster_id');
    }
}
