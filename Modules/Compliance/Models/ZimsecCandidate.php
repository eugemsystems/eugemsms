<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\ZimsecCandidateFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\AdHocCharge;
use Modules\People\Models\Student;

/**
 * Book H3 CMP-01 §2/BR-CMP-01-001. Bio-data columns are populated
 * once, by `DeriveZimsecCandidatesAction`, copied straight from
 * `Student` — never re-keyed, never edited on this model.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $registration_id
 * @property int $student_id
 * @property string|null $candidate_number
 * @property string $surname
 * @property string $forenames
 * @property Carbon $date_of_birth
 * @property string $gender
 * @property string|null $national_registration_no
 * @property string|null $national_registration_no_hash
 * @property string|null $birth_certificate_no
 * @property string|null $birth_certificate_no_hash
 * @property array<int, array{code: string, name: string, is_resit: bool}> $subject_entries
 * @property int $subject_count
 * @property bool $is_repeat_candidate
 * @property string|null $previous_candidate_no
 * @property array<int, mixed>|null $special_arrangements
 * @property int $entry_fee_minor
 * @property string $currency
 * @property int|null $ad_hoc_charge_id
 * @property bool $fee_paid
 * @property string $validation_status
 * @property array<int, array{field: string, severity: string, message: string}>|null $validation_errors
 * @property int|null $statement_of_entry_id
 * @property bool $statement_confirmed
 * @property string $status
 */
class ZimsecCandidate extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ZimsecCandidateFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'registration_id', 'student_id', 'candidate_number', 'surname', 'forenames',
        'date_of_birth', 'gender', 'national_registration_no', 'national_registration_no_hash',
        'birth_certificate_no', 'birth_certificate_no_hash', 'subject_entries', 'subject_count',
        'is_repeat_candidate', 'previous_candidate_no', 'special_arrangements', 'entry_fee_minor',
        'currency', 'ad_hoc_charge_id', 'fee_paid', 'validation_status', 'validation_errors',
        'statement_of_entry_id', 'statement_confirmed', 'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'national_registration_no' => 'encrypted',
            'birth_certificate_no' => 'encrypted',
            'subject_entries' => 'array',
            'is_repeat_candidate' => 'boolean',
            'special_arrangements' => 'array',
            'fee_paid' => 'boolean',
            'validation_errors' => 'array',
            'statement_confirmed' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ZimsecCandidateFactory::new();
    }

    /**
     * @return BelongsTo<ZimsecRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(ZimsecRegistration::class, 'registration_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<AdHocCharge, $this>
     */
    public function adHocCharge(): BelongsTo
    {
        return $this->belongsTo(AdHocCharge::class, 'ad_hoc_charge_id');
    }
}
