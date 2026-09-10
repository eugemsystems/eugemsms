<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Academic\Database\Factories\AssessmentTypeFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book D ACA-05 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property string $default_weight_percent
 * @property bool $appears_on_report_card
 * @property bool $is_examination
 * @property int|null $sort_order
 */
class AssessmentType extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AssessmentTypeFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'name', 'category', 'default_weight_percent',
        'appears_on_report_card', 'is_examination', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'appears_on_report_card' => 'boolean',
            'is_examination' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AssessmentTypeFactory::new();
    }
}
