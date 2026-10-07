<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\StaffAppraisalRubricFactory;

/**
 * Book C PPL-04 — the structured appraisal rubric. Same criterion/descriptor-levels
 * shape as `Modules\Academic\Models\ObservationRubric`.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property array<int, array<string, mixed>> $criteria
 */
class StaffAppraisalRubric extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffAppraisalRubricFactory> */
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
        return StaffAppraisalRubricFactory::new();
    }
}
