<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;
use Modules\Wallet\Domain\DataObjects\ProcessWalletSaleData;
use Modules\Wallet\Domain\Exceptions\WalletAlreadyNegativeException;
use Modules\Wallet\Models\StudentWallet;
use Modules\Wallet\Models\WalletSale;
use Throwable;

/**
 * ACT-SyncOfflineWalletSale (Book H3 FIN-14 §4 ⭐/BR-FIN-14-011/012/
 * 013 (AC-FIN-14-004/005)). A sale that already happened at the till
 * while offline — refusing to honour it is not workable (the learner
 * already ate the sandwich). Idempotent on `offline_reference`
 * (`ProcessWalletSaleAction` itself checks this first). If the wallet
 * is ALREADY negative from an earlier sync, this one is refused
 * outright (BR-FIN-14-013) — that is the actual enforcement
 * mechanism; a sync that would merely CAUSE the wallet to go negative
 * is always honoured and notifies the guardian immediately.
 */
final class SyncOfflineWalletSaleAction extends Action
{
    public function __construct(
        private readonly ProcessWalletSaleAction $processSale,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(ProcessWalletSaleData $data): WalletSale
    {
        $wallet = $data->paymentMethod === 'wallet'
            ? StudentWallet::where('school_id', $data->schoolId)->where('student_id', $data->studentId)->firstOrFail()
            : null;

        if ($wallet !== null && $wallet->balance_minor < 0) {
            throw WalletAlreadyNegativeException::forWallet($wallet->id, $wallet->balance_minor);
        }

        $sale = $this->processSale->execute($data, allowNegative: true);

        if ($wallet !== null) {
            $wallet = $wallet->fresh();

            if ($wallet->balance_minor < 0) {
                $this->notifyGuardian($wallet);
            }
        }

        return $sale;
    }

    private function notifyGuardian(StudentWallet $wallet): void
    {
        $student = Student::find($wallet->student_id);

        if ($student === null) {
            return;
        }

        $link = StudentGuardian::query()
            ->where('student_id', $student->id)
            ->where('is_primary_contact', true)
            ->where('status', 'active')
            ->with('guardian')
            ->first();

        if ($link === null || $link->guardian === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $wallet->school_id,
                notificationKey: 'wallet.negative_balance',
                recipientType: 'guardian',
                addresses: ['sms' => (string) $link->guardian->primary_phone, 'email' => (string) $link->guardian->email],
                context: [
                    'student' => ['first_name' => $student->first_name, 'last_name' => $student->last_name],
                    'balance_minor' => $wallet->balance_minor,
                ],
                recipientId: $link->guardian->id,
                relatedType: 'student_wallet',
                relatedId: $wallet->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // Non-blocking — the sale is already durable.
        }
    }
}
