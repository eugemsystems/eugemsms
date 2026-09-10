<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Wallet\Domain\DataObjects\CreateStudentWalletData;
use Modules\Wallet\Models\StudentWallet;

/**
 * ACT-CreateStudentWallet (Book H3 FIN-14 §2).
 */
final class CreateStudentWalletAction extends Action
{
    public function execute(CreateStudentWalletData $data): StudentWallet
    {
        $existing = StudentWallet::where('school_id', $data->schoolId)->where('student_id', $data->studentId)->first();

        if ($existing !== null) {
            throw ValidationException::withMessages(['studentId' => 'This learner already has a wallet.']);
        }

        return $this->transaction(fn (): StudentWallet => StudentWallet::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'balance_minor' => 0,
            'currency' => $data->currency,
            'liability_account_id' => $data->liabilityAccountId,
            'status' => 'active',
        ]));
    }
}
