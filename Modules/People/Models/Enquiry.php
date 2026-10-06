<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Database\Factories\EnquiryFactory;

/**
 * Book C PPL-02 §2. The top of the admissions funnel.
 *
 * @property int $id
 * @property int $school_id
 * @property string $ulid
 * @property int|null $intake_id
 * @property string $source
 * @property string $enquirer_name
 * @property string|null $enquirer_phone
 * @property string|null $enquirer_email
 * @property string|null $learner_name
 * @property Carbon|null $learner_dob
 * @property int|null $interested_grade_level_id
 * @property string|null $interested_residency
 * @property string|null $message
 * @property string $stage
 * @property string|null $lost_reason
 * @property int|null $assigned_to
 * @property Carbon|null $next_follow_up_on
 * @property int|null $application_id
 */
class Enquiry extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EnquiryFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'enquiries';

    public $timestamps = true;

    protected $fillable = ['school_id', 'intake_id', 'source', 'enquirer_name', 'enquirer_phone', 'enquirer_email', 'learner_name', 'learner_dob', 'interested_grade_level_id', 'interested_residency', 'message', 'stage', 'lost_reason', 'assigned_to', 'next_follow_up_on', 'application_id'];

    protected function casts(): array
    {
        return [
            'learner_dob' => 'date',
            'next_follow_up_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EnquiryFactory::new();
    }
}
