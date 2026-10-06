<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\SponsorshipBeneficiaryFactory;

/**
 * Book C PPL-03 §3. A learner funded by a sponsorship, optionally with a performance condition.
 *
 * @property int $id
 * @property int $school_id
 * @property int $sponsorship_id
 * @property int $student_id
 * @property int|null $fee_liability_id
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string $status
 * @property string|null $performance_condition
 * @property bool|null $condition_met
 */
class SponsorshipBeneficiary extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SponsorshipBeneficiaryFactory> */
    use HasFactory;

    protected $table = 'sponsorship_beneficiaries';

    public $timestamps = false;

    protected $fillable = ['school_id', 'sponsorship_id', 'student_id', 'fee_liability_id', 'starts_on', 'ends_on', 'status', 'performance_condition', 'condition_met'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'condition_met' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SponsorshipBeneficiaryFactory::new();
    }
}
