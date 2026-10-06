<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Database\Factories\EntranceExamFactory;

/**
 * Book C PPL-02 §2. An entrance examination sitting for an intake.
 *
 * @property int $id
 * @property int $school_id
 * @property string $ulid
 * @property int $intake_id
 * @property string $name
 * @property Carbon $exam_date
 * @property string $start_time
 * @property string|null $venue
 * @property int|null $capacity
 * @property array<int, array<string, mixed>> $papers
 * @property string|null $pass_mark_percent
 * @property string $status
 */
class EntranceExam extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EntranceExamFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'entrance_exams';

    public $timestamps = false;

    protected $fillable = ['school_id', 'intake_id', 'name', 'exam_date', 'start_time', 'venue', 'capacity', 'papers', 'pass_mark_percent', 'status'];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'papers' => 'array',
            'pass_mark_percent' => 'decimal:2',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EntranceExamFactory::new();
    }
}
