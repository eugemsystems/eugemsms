<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\RecordConsentData;
use Modules\Compliance\Models\Consent;
use Modules\Compliance\Models\PrivacyNotice;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordConsent (Book H3 CMP-03 §3 ⭐/BR-CMP-03-001/002). Every
 * consent records the privacy notice version IN FORCE AT THE TIME —
 * resolved here from `privacy_notices` (the latest version whose
 * `effective_from` has passed), never taken as a caller-supplied
 * value, so a consent can never be recorded against a version that
 * wasn't actually live. Append-only: this creates a row and never
 * updates one — `WithdrawConsentAction` is the only thing that ever
 * touches an existing consent afterwards.
 */
final class RecordConsentAction extends Action
{
    public function execute(RecordConsentData $data): Consent
    {
        $notice = PrivacyNotice::where('school_id', $data->schoolId)
            ->whereDate('effective_from', '<=', Carbon::now()->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        return $this->transaction(fn (): Consent => Consent::create([
            'school_id' => $data->schoolId,
            'consent_type_id' => $data->consentTypeId,
            'subject_type' => $data->subjectType,
            'subject_id' => $data->subjectId,
            'granted_by_type' => $data->grantedByType,
            'granted_by_id' => $data->grantedById,
            'granted' => $data->granted,
            'granted_at' => Carbon::now(),
            'method' => $data->method,
            'notice_version' => $notice !== null ? $notice->version : 'unversioned',
            'document_file_id' => $data->documentFileId,
            'witness_staff_id' => $data->witnessStaffId,
            'ip_address' => $data->ipAddress,
            'expires_on' => $data->expiresOn,
        ]));
    }
}
