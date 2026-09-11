<?php

declare(strict_types=1);

namespace Modules\People\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\House;
use Modules\People\Database\Factories\AlumnusFactory;

/**
 * Book K PPL-06 §2/BR-PPL-06-001/002/003 — see `CreateAlumniRecordAction`
 * for why `academic_summary_snapshot` is frozen once, at graduation,
 * and never recomputed. Table is `alumni` (irregular Latin plural,
 * used as the table name per spec even though this model represents
 * ONE alumnus) — `protected $table` is set explicitly rather than
 * trusting Eloquent's pluralizer to guess it correctly.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int|null $user_id
 * @property string $admission_number
 * @property int $graduation_year
 * @property int $final_grade_level_id
 * @property int|null $final_house_id
 * @property array<string, mixed> $academic_summary_snapshot
 * @property string|null $current_occupation
 * @property string|null $current_employer
 * @property string|null $further_education
 * @property string|null $current_city
 * @property string|null $current_country
 * @property bool $is_notable
 * @property array<string, mixed>|null $contact_preferences
 * @property Carbon|null $last_contact_at
 * @property string $status
 */
class Alumnus extends Model
{
    use BelongsToSchool;

    protected $table = 'alumni';

    /** @use HasFactory<AlumnusFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'student_id', 'user_id', 'admission_number', 'graduation_year', 'final_grade_level_id',
        'final_house_id', 'academic_summary_snapshot', 'current_occupation', 'current_employer',
        'further_education', 'current_city', 'current_country', 'is_notable', 'contact_preferences',
        'last_contact_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'academic_summary_snapshot' => 'array',
            'is_notable' => 'boolean',
            'contact_preferences' => 'array',
            'last_contact_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AlumnusFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<GradeLevel, $this>
     */
    public function finalGradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'final_grade_level_id');
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function finalHouse(): BelongsTo
    {
        return $this->belongsTo(House::class, 'final_house_id');
    }

    /**
     * @return HasMany<AlumniCareerUpdate, $this>
     */
    public function careerUpdates(): HasMany
    {
        return $this->hasMany(AlumniCareerUpdate::class, 'alumnus_id');
    }
}
