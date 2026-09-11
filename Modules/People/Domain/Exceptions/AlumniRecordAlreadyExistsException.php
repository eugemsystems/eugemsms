<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

class AlumniRecordAlreadyExistsException extends DomainException
{
    public static function forStudent(int $studentId): self
    {
        return new self(
            "Student #{$studentId} already has an alumni record.",
            ['student_id' => $studentId],
        );
    }

    public function errorCode(): string
    {
        return 'ALUMNI_RECORD_ALREADY_EXISTS';
    }
}
