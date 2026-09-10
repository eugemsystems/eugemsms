<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\Actions\PostJournalAction;
use Modules\Finance\Domain\DataObjects\JournalLineData;
use Modules\Finance\Domain\DataObjects\PostJournalData;
use Modules\Stores\Domain\DataObjects\DisposeAssetData;
use Modules\Stores\Domain\Events\AssetDisposed;
use Modules\Stores\Domain\Exceptions\DisposalRequiresApprovalException;
use Modules\Stores\Models\AssetDisposal;
use Modules\Stores\Models\AssetMovement;
use Modules\Stores\Models\FixedAsset;

/**
 * ACT-DisposeAsset (Book H1 FIN-10 §6 ⭐/BR-FIN-10-013/014/
 * AC-FIN-10-006). Gain or loss computes against net book value AT
 * THE DISPOSAL DATE — this action assumes depreciation has already
 * been posted up to that date via the normal monthly run; it does not
 * itself catch up a partial period. Theft/loss methods only fire the
 * event that a real insurance-claim/incident workflow would consume
 * (BR-FIN-10-014) — no `BRD-07`/security-incident integration exists
 * yet for this action to call into, an honest deferral matching this
 * module's own established boundary shape.
 */
final class DisposeAssetAction extends Action
{
    public function __construct(
        private readonly PostJournalAction $postJournal,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(DisposeAssetData $data): AssetDisposal
    {
        $asset = FixedAsset::with('category')->findOrFail($data->assetId);

        if ($asset->status === 'disposed') {
            throw ValidationException::withMessages(['assetId' => "Asset #{$asset->id} has already been disposed."]);
        }

        $nbv = $asset->net_book_value_minor;
        $gainLoss = $data->proceedsMinor - $nbv;

        $threshold = (int) $this->settings->get('assets.disposal_approval_threshold_minor', new ScopeChain(schoolId: $asset->school_id));

        if ($nbv >= $threshold && $data->approvedByUserId === null) {
            throw DisposalRequiresApprovalException::aboveThreshold($nbv, $threshold);
        }

        if ($data->proceedsMinor > 0 && $data->proceedsAccountId === null) {
            throw ValidationException::withMessages(['proceedsAccountId' => 'A proceeds account is required when disposal proceeds are greater than zero.']);
        }

        $currency = Currency::from($asset->currency);

        return $this->transaction(function () use ($asset, $data, $currency, $nbv, $gainLoss): AssetDisposal {
            $lines = [
                new JournalLineData(accountId: $asset->category->accum_depreciation_account_id, direction: 'DR', amount: Money::of($asset->accumulated_depreciation_minor, $currency), costCentreId: $asset->cost_centre_id),
            ];

            if ($data->proceedsMinor > 0) {
                $lines[] = new JournalLineData(accountId: $data->proceedsAccountId, direction: 'DR', amount: Money::of($data->proceedsMinor, $currency));
            }

            if ($gainLoss < 0) {
                $lines[] = new JournalLineData(accountId: $asset->category->disposal_account_id, direction: 'DR', amount: Money::of(abs($gainLoss), $currency), costCentreId: $asset->cost_centre_id);
            }

            $lines[] = new JournalLineData(accountId: $asset->category->asset_account_id, direction: 'CR', amount: Money::of($asset->acquisition_cost_minor, $currency), costCentreId: $asset->cost_centre_id);

            if ($gainLoss > 0) {
                $lines[] = new JournalLineData(accountId: $asset->category->disposal_account_id, direction: 'CR', amount: Money::of($gainLoss, $currency), costCentreId: $asset->cost_centre_id);
            }

            $journal = $this->postJournal->execute(new PostJournalData(
                schoolId: $asset->school_id,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
                journalType: 'ASSET_DISPOSAL',
                narration: "Asset disposal — {$asset->name} ({$asset->asset_tag})",
                lines: $lines,
                effectiveAt: $data->disposalDate,
                postedByUserId: $data->performedByUserId,
                sourceType: 'fixed_asset',
                sourceId: $asset->id,
            ));

            $disposal = AssetDisposal::create([
                'school_id' => $asset->school_id,
                'asset_id' => $asset->id,
                'disposal_date' => $data->disposalDate->toDateString(),
                'disposal_method' => $data->disposalMethod,
                'proceeds_minor' => $data->proceedsMinor,
                'currency' => $asset->currency,
                'nbv_at_disposal_minor' => $nbv,
                'gain_loss_minor' => $gainLoss,
                'buyer' => $data->buyer,
                'reason' => $data->reason,
                'approved_by' => $data->approvedByUserId,
                'journal_id' => $journal->id,
            ]);

            $fromStatus = $asset->status;
            $asset->update(['status' => 'disposed', 'net_book_value_minor' => 0]);

            AssetMovement::create([
                'school_id' => $asset->school_id,
                'asset_id' => $asset->id,
                'movement_type' => 'status_change',
                'from_value' => $fromStatus,
                'to_value' => 'disposed',
                'reason' => $data->reason,
                'journal_id' => $journal->id,
                'performed_by' => $data->performedByUserId,
                'occurred_at' => $data->disposalDate,
            ]);

            event(new AssetDisposed($disposal));

            return $disposal;
        });
    }
}
