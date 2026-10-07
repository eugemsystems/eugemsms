<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\GuardianContactUpdateFactory;

/**
 * Book C PPL-03 §6/BR-PPL-03-021. A guardian's requested change to phone, email or address, held until staff approve it.
 *
 * @property int $id
 * @property int $school_id
 * @property int $guardian_id
 * @property array<string, string|null> $changes
 * @property string $status
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon $created_at
 */
class GuardianContactUpdate extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<GuardianContactUpdateFactory> */
    use HasFactory;

    protected $table = 'guardian_contact_updates';

    public $timestamps = false;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $fillable = ['school_id', 'guardian_id', 'changes', 'status', 'requested_by', 'decided_by', 'decided_at', 'decision_note', 'created_at'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'decided_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return GuardianContactUpdateFactory::new();
    }
}
