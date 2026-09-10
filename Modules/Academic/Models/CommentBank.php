<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Academic\Database\Factories\CommentBankFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book D ACA-05 §2.
 *
 * @property int $id
 * @property int $school_id
 * @property string $scope
 * @property int|null $subject_id
 * @property string|null $grade_band
 * @property string $text
 * @property int $usage_count
 * @property int $created_by
 * @property bool $is_active
 */
class CommentBank extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<CommentBankFactory> */
    use HasFactory;

    protected $fillable = ['school_id', 'scope', 'subject_id', 'grade_band', 'text', 'usage_count', 'created_by', 'is_active'];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return CommentBankFactory::new();
    }
}
