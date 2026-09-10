<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Models\SubjectAccessRequest;
use Modules\Core\Domain\Actions\Action;

final class VerifyRequesterIdentityAction extends Action
{
    public function execute(int $requestId, int $verifiedByUserId, string $verificationMethod): SubjectAccessRequest
    {
        return $this->transaction(function () use ($requestId, $verifiedByUserId, $verificationMethod): SubjectAccessRequest {
            $request = SubjectAccessRequest::findOrFail($requestId);

            $request->update([
                'identity_verified' => true,
                'verification_method' => $verificationMethod,
                'verified_by' => $verifiedByUserId,
                'status' => 'compiling',
            ]);

            return $request;
        });
    }
}
