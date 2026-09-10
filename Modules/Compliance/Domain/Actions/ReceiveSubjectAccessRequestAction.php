<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\ReceiveSubjectAccessRequestData;
use Modules\Compliance\Models\SubjectAccessRequest;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;

/**
 * ACT-ReceiveSubjectAccessRequest (Book H3 CMP-03 §3 ⭐/BR-CMP-03-008
 * (AC-CMP-03-002)). `due_by` is a statutory window — versioned config
 * (`compliance.dsar_response_days`), never a hard-coded constant, per
 * this book's own doctrine for regulatory figures. `requires_confirmation`:
 * the default here (30 days) is a placeholder pending confirmation of
 * the Cyber and Data Protection Act [Chapter 12:07]'s own literal
 * response window — do not treat it as authoritative without checking
 * the Act's text.
 */
final class ReceiveSubjectAccessRequestAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(ReceiveSubjectAccessRequestData $data): SubjectAccessRequest
    {
        $responseDays = (int) $this->settings->get('compliance.dsar_response_days', new ScopeChain(schoolId: $data->schoolId));

        return $this->transaction(fn (): SubjectAccessRequest => SubjectAccessRequest::create([
            'school_id' => $data->schoolId,
            'request_type' => $data->requestType,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
            'requester_name' => $data->requesterName,
            'requester_relationship' => $data->requesterRelationship,
            'identity_verified' => false,
            'received_at' => Carbon::now(),
            'due_by' => Carbon::now()->addDays($responseDays)->toDateString(),
            'scope_description' => $data->scopeDescription,
            'status' => 'received',
        ]));
    }
}
