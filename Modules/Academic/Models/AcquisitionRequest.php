<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\AcquisitionRequestFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book K ACA-10 §2/BR-ACA-10-010. Routes into `FIN-08`'s ordinary
 * procurement pipeline once approved — not built in this codebase yet
 * (see the owning migration's docblock), so this module only carries
 * a request as far as `requested`/`rejected`.
 *
 * @property int $id
 * @property int $school_id
 * @property string $requested_title
 * @property int $requested_by
 * @property int $copies_requested
 * @property int|null $estimated_cost_minor
 * @property string $status
 */
class AcquisitionRequest extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AcquisitionRequestFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'requested_title', 'requested_by', 'copies_requested', 'estimated_cost_minor', 'status'];

    protected function casts(): array
    {
        return [
            'copies_requested' => 'integer',
            'estimated_cost_minor' => 'integer',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return AcquisitionRequestFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
