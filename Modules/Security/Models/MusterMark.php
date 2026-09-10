<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Security\Database\Factories\MusterMarkFactory;

/**
 * Book H2 OPS-06 §3 ⭐⭐/BR-OPS-06-008/009 — see this table's own
 * migration docblock for why it exists beyond the spec's literal
 * schema.
 *
 * @property int $id
 * @property int $school_id
 * @property int $drill_id
 * @property string $person_type
 * @property int $person_id
 * @property string|null $assembly_point
 * @property Carbon $marked_present_at
 * @property int $marked_by
 */
class MusterMark extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MusterMarkFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'drill_id', 'person_type', 'person_id', 'assembly_point', 'marked_present_at', 'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'marked_present_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MusterMarkFactory::new();
    }

    /**
     * @return BelongsTo<EmergencyDrill, $this>
     */
    public function drill(): BelongsTo
    {
        return $this->belongsTo(EmergencyDrill::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
