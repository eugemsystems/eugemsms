<?php

declare(strict_types=1);

namespace Modules\Transport\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;
use Modules\Transport\Database\Factories\DriverFactory;

/**
 * Book H2 OPS-01 §2/BR-OPS-01-004. `licence_number` is cast
 * `encrypted` — see this table's own migration docblock.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $staff_id
 * @property string $licence_number
 * @property array<int, string> $licence_classes
 * @property Carbon $licence_expires_on
 * @property string|null $defensive_driving_cert
 * @property Carbon|null $defensive_expires_on
 * @property Carbon|null $medical_certificate_on
 * @property Carbon|null $medical_expires_on
 * @property Carbon|null $retest_due_on
 * @property int|null $years_experience
 * @property string $status
 * @property string|null $suspension_reason
 * @property int $incident_count
 */
class Driver extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<DriverFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'staff_id', 'licence_number', 'licence_classes', 'licence_expires_on',
        'defensive_driving_cert', 'defensive_expires_on', 'medical_certificate_on', 'medical_expires_on',
        'retest_due_on', 'years_experience', 'status', 'suspension_reason', 'incident_count',
    ];

    protected function casts(): array
    {
        return [
            'licence_number' => 'encrypted',
            'licence_classes' => 'array',
            'licence_expires_on' => 'date',
            'defensive_expires_on' => 'date',
            'medical_certificate_on' => 'date',
            'medical_expires_on' => 'date',
            'retest_due_on' => 'date',
        ];
    }

    public function hasExpiredDocuments(): bool
    {
        $today = now()->startOfDay();

        if ($this->licence_expires_on->lessThan($today)) {
            return true;
        }

        if ($this->medical_expires_on !== null && $this->medical_expires_on->lessThan($today)) {
            return true;
        }

        if ($this->defensive_expires_on !== null && $this->defensive_expires_on->lessThan($today)) {
            return true;
        }

        return false;
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return DriverFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
