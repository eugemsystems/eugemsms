<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\SubjectSelectionSubmission;

/**
 * Book D ACA-02 §9. Fired on the *final* (school) approval, once
 * allocation into `learner_subject_enrolments` has happened.
 */
final class SubjectSelectionApproved
{
    public function __construct(public readonly SubjectSelectionSubmission $submission) {}
}
