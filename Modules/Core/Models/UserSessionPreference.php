<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-03 §2. A user's last active (school, academic_year, term),
 * one row per (user, school).
 *
 * @property int $id
 * @property int $user_id
 * @property int $school_id
 * @property int|null $academic_year_id
 * @property int|null $term_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read School $school
 * @property-read AcademicYear|null $academicYear
 * @property-read Term|null $term
 */
class UserSessionPreference extends Model
{
    protected $fillable = [
        'user_id',
        'school_id',
        'academic_year_id',
        'term_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
