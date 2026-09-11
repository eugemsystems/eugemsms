<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateQuestionBankItemData
{
    /**
     * @param  array<int, mixed>|null  $options
     * @param  array<int, mixed>|null  $correctAnswer
     */
    public function __construct(
        public int $schoolId,
        public int $subjectId,
        public string $itemType,
        public string $difficulty,
        public string $prompt,
        public float $maxMark,
        public bool $isAutoMarkable,
        public int $createdByUserId,
        public ?string $topic = null,
        public ?string $syllabusObjectiveRef = null,
        public ?int $promptImageFileId = null,
        public ?array $options = null,
        public ?array $correctAnswer = null,
    ) {}
}
