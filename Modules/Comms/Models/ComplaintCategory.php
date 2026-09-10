<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Comms\Database\Factories\ComplaintCategoryFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book I COM-08 §2 ⭐/BR-COM-08-003/006. See the owning migration's
 * docblock for `is_safeguarding_trigger`.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property int|null $default_assignee_role_id
 * @property int $sla_hours
 * @property bool $is_safeguarding_trigger
 */
class ComplaintCategory extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ComplaintCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'code', 'name', 'default_assignee_role_id', 'sla_hours', 'is_safeguarding_trigger',
    ];

    protected function casts(): array
    {
        return [
            'is_safeguarding_trigger' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ComplaintCategoryFactory::new();
    }
}
