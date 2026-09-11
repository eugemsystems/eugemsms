<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\AlumniCareerUpdateFactory;

/**
 * Book K PPL-06 §2/BR-PPL-06-004. Self-reported; still displayed
 * (clearly marked) before verification.
 *
 * @property int $id
 * @property int $school_id
 * @property int $alumnus_id
 * @property string $update_type
 * @property string $title
 * @property string|null $institution_or_employer
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_current
 * @property bool $verified
 * @property Carbon $submitted_at
 */
class AlumniCareerUpdate extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AlumniCareerUpdateFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'alumnus_id', 'update_type', 'title', 'institution_or_employer', 'starts_on', 'ends_on',
        'is_current', 'verified', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'verified' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AlumniCareerUpdateFactory::new();
    }

    /**
     * @return BelongsTo<Alumnus, $this>
     */
    public function alumnus(): BelongsTo
    {
        return $this->belongsTo(Alumnus::class);
    }
}
