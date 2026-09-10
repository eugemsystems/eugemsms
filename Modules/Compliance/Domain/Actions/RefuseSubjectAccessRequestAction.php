<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Models\SubjectAccessRequest;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RefuseSubjectAccessRequest (Book H3 CMP-03 §3/BR-CMP-03-010).
 * Erasure requests assessed against retention obligations, or any
 * other request refused for a lawful reason — the ground is always
 * recorded, never a bare rejection.
 */
final class RefuseSubjectAccessRequestAction extends Action
{
    public function execute(int $requestId, int $handledByUserId, string $refusalGrounds): SubjectAccessRequest
    {
        return $this->transaction(function () use ($requestId, $handledByUserId, $refusalGrounds): SubjectAccessRequest {
            $request = SubjectAccessRequest::findOrFail($requestId);

            $request->update([
                'status' => 'refused',
                'refusal_grounds' => $refusalGrounds,
                'handled_by' => $handledByUserId,
            ]);

            return $request;
        });
    }
}
