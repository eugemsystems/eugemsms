<?php

declare(strict_types=1);

namespace Modules\Reporting\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Reporting\Database\Factories\ReportDefinitionFactory;

/**
 * Book H3 FIN-12 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $report_type
 * @property array<string, mixed> $structure
 * @property int $comparative_periods
 * @property bool $show_variance
 * @property bool $show_budget
 * @property bool $is_system
 */
class ReportDefinition extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReportDefinitionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'code', 'name', 'report_type', 'structure', 'comparative_periods',
        'show_variance', 'show_budget', 'is_system',
    ];

    protected function casts(): array
    {
        return [
            'structure' => 'array',
            'show_variance' => 'boolean',
            'show_budget' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ReportDefinitionFactory::new();
    }
}
