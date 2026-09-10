<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\StatutoryDocumentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\People\Models\Staff;

/**
 * Book H3 CMP-04 §2/BR-CMP-04-005/006.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $document_type
 * @property string|null $reference_number
 * @property string $issuing_authority
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property int|null $file_id
 * @property int $renewal_lead_days
 * @property int|null $responsible_staff_id
 * @property string $status
 */
class StatutoryDocument extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StatutoryDocumentFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'document_type', 'reference_number', 'issuing_authority', 'issued_on', 'expires_on',
        'file_id', 'renewal_lead_days', 'responsible_staff_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StatutoryDocumentFactory::new();
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function responsibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'responsible_staff_id');
    }

    /**
     * BR-CMP-04-006: an expired operating licence, insurance or fire
     * certificate is a critical-alert category.
     */
    public function isCritical(): bool
    {
        return in_array($this->document_type, ['operating_licence', 'insurance', 'fire_certificate'], true);
    }
}
