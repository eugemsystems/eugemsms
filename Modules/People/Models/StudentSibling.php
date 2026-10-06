<?php

declare(strict_types=1);

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\People\Database\Factories\StudentSiblingFactory;

/**
 * Book C PPL-01 §2/BR-PPL-01-018. Symmetric sibling links: each link is stored in both directions.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property int $sibling_student_id
 * @property string $relationship
 * @property int|null $birth_order
 * @property int|null $linked_by
 */
class StudentSibling extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentSiblingFactory> */
    use HasFactory;

    protected $table = 'student_siblings';

    public $timestamps = false;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $fillable = ['school_id', 'student_id', 'sibling_student_id', 'relationship', 'birth_order', 'linked_by', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentSiblingFactory::new();
    }
}
