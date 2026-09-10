<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Models\File;
use Modules\Intelligence\Database\Factories\BoardPackFactory;

/**
 * Book J INT-02 §2/BR-INT-02-006.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $term_id
 * @property array<int, string> $sections_included
 * @property int|null $document_id
 * @property int $generated_by
 * @property Carbon $generated_at
 */
class BoardPack extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BoardPackFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'term_id', 'sections_included', 'document_id', 'generated_by', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'sections_included' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return BoardPackFactory::new();
    }

    /**
     * @return BelongsTo<File, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(File::class, 'document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
