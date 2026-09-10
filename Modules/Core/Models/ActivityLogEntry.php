<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Book A CORE-08 §2. `school_id` nullable — deliberately not
 * `BelongsToSchool` (see the migration's docblock); every writer
 * supplies `school_id` explicitly, including null.
 *
 * @property int $id
 * @property int|null $school_id
 * @property string $log_name
 * @property string $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $causer_type
 * @property int|null $causer_id
 * @property string|null $event
 * @property array{old?: array<string, mixed>, attributes?: array<string, mixed>}|null $properties
 * @property string|null $batch_uuid
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property int|null $impersonator_id
 * @property int|null $academic_year_id
 * @property int|null $term_id
 * @property Carbon $created_at
 */
class ActivityLogEntry extends Model
{
    protected $table = 'activity_log';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'log_name', 'description', 'subject_type', 'subject_id',
        'causer_type', 'causer_id', 'event', 'properties', 'batch_uuid', 'ip_address',
        'user_agent', 'request_id', 'impersonator_id', 'academic_year_id', 'term_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }
}
