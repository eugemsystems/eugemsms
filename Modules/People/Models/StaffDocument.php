<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\StaffDocumentFactory;

/**
 * Book C PPL-04 §2/BR-PPL-04-018.
 *
 * @property int $id
 * @property int $school_id
 * @property int $staff_id
 * @property string $document_type
 * @property int $file_id
 * @property string|null $reference_number
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property bool $is_verified
 */
class StaffDocument extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StaffDocumentFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * Book C PPL-04 §4/BR-PPL-04-018 — police clearance, medical
     * certificate, and work permit are the three document types the
     * spec names as compliance-critical when expired.
     *
     * @var array<int, string>
     */
    public const array COMPLIANCE_CRITICAL_TYPES = ['police_clearance', 'medical_certificate', 'work_permit'];

    protected $fillable = [
        'school_id', 'staff_id', 'document_type', 'file_id', 'reference_number', 'issued_on', 'expires_on', 'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
            'is_verified' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StaffDocumentFactory::new();
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    public function isComplianceCritical(): bool
    {
        return in_array($this->document_type, self::COMPLIANCE_CRITICAL_TYPES, true);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
