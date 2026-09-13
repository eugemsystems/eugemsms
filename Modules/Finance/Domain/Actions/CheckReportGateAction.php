<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Finance\Domain\DataObjects\CheckReportGateData;
use Modules\Finance\Domain\DataObjects\ReportGateCheckResult;
use Modules\Finance\Models\Invoice;
use Modules\Finance\Models\ReportGateOverride;

/**
 * ACT-CheckReportGate (Book B FIN-03 §4/BR-FIN-03-018/AC-FIN-03-007).
 * Only components flagged `counts_toward_report_gate` count toward the
 * threshold. Balance is tracked per invoice, not per line, so a
 * mixed invoice (some gate-counting components, some not) apportions
 * its cached `balance_minor` by the ratio of gate-counting `net_minor`
 * to the invoice's own total `net_minor` — the same kind of documented
 * rounding approximation `IssueInvoicesForAssignmentAction` already
 * accepts for split-invoice gross/discount shares, rather than
 * requiring a per-line balance this schema doesn't track.
 */
final class CheckReportGateAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(CheckReportGateData $data): ReportGateCheckResult
    {
        $scope = new ScopeChain(schoolId: $data->schoolId);
        $enabled = (bool) $this->settings->get('finance.report_gate_enabled', $scope);
        $thresholdMinor = (int) $this->settings->get('finance.report_gate_threshold_minor', $scope);
        $currency = (string) $this->settings->get('finance.report_gate_threshold_currency', $scope);

        $hasOverride = ReportGateOverride::where('school_id', $data->schoolId)
            ->where('student_id', $data->studentId)
            ->where('term_id', $data->termId)
            ->exists();

        $gateBalanceMinor = 0;

        $invoices = Invoice::query()
            ->where('student_id', $data->studentId)
            ->where('currency', $currency)
            ->where('balance_minor', '>', 0)
            ->with('lines.component')
            ->get();

        foreach ($invoices as $invoice) {
            $gateNet = $invoice->lines->filter(fn ($line) => $line->component->counts_toward_report_gate)->sum('net_minor');

            if ($gateNet <= 0) {
                continue;
            }

            $gateBalanceMinor += $invoice->net_minor > 0
                ? (int) round($invoice->balance_minor * ($gateNet / $invoice->net_minor))
                : 0;
        }

        $isWithheld = $enabled && ! $hasOverride && $gateBalanceMinor > $thresholdMinor;

        return new ReportGateCheckResult(
            isWithheld: $isWithheld,
            gateBalanceMinor: $gateBalanceMinor,
            currency: $currency,
            thresholdMinor: $thresholdMinor,
            hasOverride: $hasOverride,
        );
    }
}
