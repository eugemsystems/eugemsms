<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Models\Student;
use Modules\Security\Database\Factories\LostPropertyFactory;

/**
 * Book H2 OPS-06 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property Carbon $found_on
 * @property string $description
 * @property string|null $found_location
 * @property int|null $found_by
 * @property int|null $photo_file_id
 * @property string $status
 * @property int|null $claimed_by_student_id
 * @property Carbon|null $claimed_at
 * @property Carbon|null $disposal_on
 */
class LostProperty extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<LostPropertyFactory> */
    use HasFactory;

    protected $table = 'lost_property';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'found_on', 'description', 'found_location', 'found_by', 'photo_file_id', 'status',
        'claimed_by_student_id', 'claimed_at', 'disposal_on',
    ];

    protected function casts(): array
    {
        return [
            'found_on' => 'date',
            'claimed_at' => 'datetime',
            'disposal_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return LostPropertyFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function foundBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'found_by');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function claimedByStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'claimed_by_student_id');
    }
}
