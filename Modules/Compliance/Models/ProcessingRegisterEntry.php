<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\ProcessingRegisterEntryFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-013. `protected $table` — the spec
 * table is `processing_register`, singular of what one row describes.
 *
 * @property int $id
 * @property int $school_id
 * @property string $activity_name
 * @property string $purpose
 * @property string $lawful_basis
 * @property array<int, string> $data_categories
 * @property array<int, string> $subject_categories
 * @property array<int, string>|null $recipients
 * @property int|null $retention_schedule_id
 * @property bool $involves_minors
 * @property bool $is_special_category
 * @property string|null $security_measures
 * @property string|null $owning_module
 * @property Carbon|null $last_reviewed_on
 */
class ProcessingRegisterEntry extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ProcessingRegisterEntryFactory> */
    use HasFactory;

    protected $table = 'processing_register';

    protected $fillable = [
        'school_id', 'activity_name', 'purpose', 'lawful_basis', 'data_categories', 'subject_categories',
        'recipients', 'retention_schedule_id', 'involves_minors', 'is_special_category',
        'security_measures', 'owning_module', 'last_reviewed_on',
    ];

    protected function casts(): array
    {
        return [
            'data_categories' => 'array',
            'subject_categories' => 'array',
            'recipients' => 'array',
            'involves_minors' => 'boolean',
            'is_special_category' => 'boolean',
            'last_reviewed_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProcessingRegisterEntryFactory::new();
    }

    /**
     * @return BelongsTo<RetentionSchedule, $this>
     */
    public function retentionSchedule(): BelongsTo
    {
        return $this->belongsTo(RetentionSchedule::class, 'retention_schedule_id');
    }
}
