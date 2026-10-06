<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\GuardianVerificationFactory;

/**
 * Book C PPL-03 §3. An identity check on a guardian before they may collect a learner.
 *
 * @property int $id
 * @property int $school_id
 * @property int $guardian_id
 * @property string $document_type
 * @property int|null $document_file_id
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property int|null $photo_file_id
 * @property string|null $notes
 */
class GuardianVerification extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GuardianVerificationFactory> */
    use HasFactory;

    protected $table = 'guardian_verification';

    public $timestamps = false;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $fillable = ['school_id', 'guardian_id', 'document_type', 'document_file_id', 'verified_by', 'verified_at', 'photo_file_id', 'notes', 'created_at'];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GuardianVerificationFactory::new();
    }
}
