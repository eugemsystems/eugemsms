<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\SubjectSelectionSubmission;

/**
 * Book D ACA-02 §9.
 */
final class SubjectSelectionSubmitted
{
    public function __construct(public readonly SubjectSelectionSubmission $submission) {}
}
