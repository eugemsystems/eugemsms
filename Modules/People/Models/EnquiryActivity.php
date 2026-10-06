<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\EnquiryActivityFactory;

/**
 * Book C PPL-02 §2. A call, visit or note against an enquiry.
 *
 * @property int $id
 * @property int $school_id
 * @property int $enquiry_id
 * @property string $activity_type
 * @property string $summary
 * @property string|null $outcome
 * @property int $performed_by
 * @property Carbon $occurred_at
 */
class EnquiryActivity extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<EnquiryActivityFactory> */
    use HasFactory;

    protected $table = 'enquiry_activities';

    public $timestamps = false;

    protected $fillable = ['school_id', 'enquiry_id', 'activity_type', 'summary', 'outcome', 'performed_by', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return EnquiryActivityFactory::new();
    }
}
