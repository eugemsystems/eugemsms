<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Boarding\Database\Factories\LaundryItemFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;

/**
 * Book F BRD-05 §2/BR-BRD-05-005/006 — per learner per cycle.
 *
 * @property int $id
 * @property int $school_id
 * @property int $cycle_id
 * @property int $student_id
 * @property int $items_out
 * @property int|null $items_back
 * @property string|null $missing_description
 * @property bool $resolved
 */
class LaundryItem extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LaundryItemFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'cycle_id', 'student_id', 'items_out', 'items_back', 'missing_description', 'resolved'];

    protected function casts(): array
    {
        return [
            'resolved' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LaundryItemFactory::new();
    }

    /**
     * @return BelongsTo<LaundryCycle, $this>
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(LaundryCycle::class, 'cycle_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
