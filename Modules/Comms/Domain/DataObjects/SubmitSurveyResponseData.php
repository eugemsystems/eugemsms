<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

final readonly class SubmitSurveyResponseData
{
    /**
     * @param  array<int, mixed>  $answersBySequence
     */
    public function __construct(
        public int $surveyId,
        public array $answersBySequence,
        public ?string $respondentType = null,
        public ?int $respondentId = null,
    ) {}
}
