<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Database\Factories\QuestionBankItemFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\File;

/**
 * Book K ACA-09 §2. Table is `question_bank` (singular) — see the
 * owning migration's docblock.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $subject_id
 * @property string|null $topic
 * @property string|null $syllabus_objective_ref
 * @property string $item_type
 * @property string $difficulty
 * @property string $prompt
 * @property int|null $prompt_image_file_id
 * @property array<string, mixed>|null $options
 * @property array<int, mixed>|null $correct_answer
 * @property string $max_mark
 * @property bool $is_auto_markable
 * @property string|null $difficulty_index
 * @property string|null $discrimination_index
 * @property int $usage_count
 * @property int $created_by
 * @property bool $is_active
 */
class QuestionBankItem extends Model
{
    use BelongsToSchool;

    protected $table = 'question_bank';

    /** @use HasFactory<QuestionBankItemFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'subject_id', 'topic', 'syllabus_objective_ref', 'item_type', 'difficulty',
        'prompt', 'prompt_image_file_id', 'options', 'correct_answer', 'max_mark', 'is_auto_markable',
        'difficulty_index', 'discrimination_index', 'usage_count', 'created_by', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_answer' => 'array',
            'is_auto_markable' => 'boolean',
            'usage_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return QuestionBankItemFactory::new();
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function promptImage(): BelongsTo
    {
        return $this->belongsTo(File::class, 'prompt_image_file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
