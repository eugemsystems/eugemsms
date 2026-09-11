<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Academic\Database\Factories\ObservationRubricFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book K ACA-11 §2/BR-ACA-11-004.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property array<int, array<string, mixed>> $criteria
 */
class ObservationRubric extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ObservationRubricFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'name', 'criteria'];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ObservationRubricFactory::new();
    }
}
