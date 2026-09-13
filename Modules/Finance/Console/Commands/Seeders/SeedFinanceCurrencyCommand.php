<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands\Seeders;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\ApproveExchangeRateAction;
use Modules\Finance\Domain\Actions\CaptureExchangeRateAction;
use Modules\Finance\Domain\Actions\RejectExchangeRateAction;
use Modules\Finance\Domain\Actions\RunFxRevaluationAction;
use Modules\Finance\Domain\DataObjects\ApproveExchangeRateData;
use Modules\Finance\Domain\DataObjects\CaptureExchangeRateData;
use Modules\Finance\Domain\DataObjects\RejectExchangeRateData;
use Modules\Finance\Domain\DataObjects\RunFxRevaluationData;
use Modules\Finance\Models\ExchangeRate;
use Modules\Finance\Models\ExchangeRateSource;

/**
 * Step 4 of the `serp:seed:finance-*` suite. Seeds a realistic run of
 * USD → ZWG exchange rate history — several `school_rate` captures
 * (auto-active, superseding each other in turn, matching how a bursar
 * updates the day's rate) plus one `school_rate_reviewed` capture left
 * `pending` and one `rejected`, so `Finance\Currency\ApproveRate` has
 * something to work with — then runs one FX revaluation.
 */
final class SeedFinanceCurrencyCommand extends Command
{
    protected $signature = 'serp:seed:finance-currency {--code=FINDEMO : The demo school code from serp:seed:finance-school-setup}';

    protected $description = 'Seed exchange rate history (active/pending/rejected) and an FX revaluation for the demo Finance school.';

    public function handle(): int
    {
        $code = mb_strtoupper((string) $this->option('code'));
        $school = School::withoutGlobalScopes()->where('code', $code)->first();

        if ($school === null) {
            $this->components->error("No school with code [{$code}] found — run serp:seed:finance-school-setup first.");

            return self::FAILURE;
        }

        SchoolContext::set($school);

        if (ExchangeRate::where('school_id', $school->id)->exists()) {
            $this->components->warn('Currency data already exists for this school — skipping. Run serp:seed:finance-fees next.');

            return self::SUCCESS;
        }

        $accountant = User::firstWhere('email', 'accountant@nyaradzo.example.zw');
        $approver = User::firstWhere('email', 'finance.approver@nyaradzo.example.zw');

        if ($accountant === null || $approver === null) {
            $this->components->error('Accountant/Finance Approver users are missing — run serp:seed:finance-users first.');

            return self::FAILURE;
        }

        $schoolRate = ExchangeRateSource::where('key', 'school_rate')->firstOrFail();
        $reviewedRate = ExchangeRateSource::where('key', 'school_rate_reviewed')->firstOrFail();

        $this->components->task('Capturing 4 weeks of daily USD → ZWG school rates', function () use ($school, $accountant, $schoolRate): bool {
            // A loosely realistic drift, not a real historical feed.
            $rate = 13.50;

            for ($weeksAgo = 4; $weeksAgo >= 0; $weeksAgo--) {
                $rate += random_int(20, 90) / 100;

                app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
                    schoolId: $school->id,
                    sourceId: $schoolRate->id,
                    fromCurrency: 'USD',
                    toCurrency: 'ZWG',
                    rate: number_format($rate, 4, '.', ''),
                    effectiveFrom: Carbon::now()->subWeeks($weeksAgo),
                    capturedByUserId: $accountant->id,
                    notes: 'Weekly bank rate update.',
                ));
            }

            return true;
        });

        $this->components->task('Capturing one pending (reviewed-source) rate for the approval queue', function () use ($school, $accountant, $reviewedRate): bool {
            app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
                schoolId: $school->id,
                sourceId: $reviewedRate->id,
                fromCurrency: 'USD',
                toCurrency: 'ZWG',
                rate: '16.2500',
                effectiveFrom: Carbon::now(),
                capturedByUserId: $accountant->id,
                notes: 'Auction rate — awaiting bursar sign-off before it takes effect.',
            ));

            return true;
        });

        $this->components->task('Capturing and rejecting one bad rate (for the audit trail)', function () use ($school, $accountant, $approver, $reviewedRate): bool {
            $badRate = app(CaptureExchangeRateAction::class)->execute(new CaptureExchangeRateData(
                schoolId: $school->id,
                sourceId: $reviewedRate->id,
                fromCurrency: 'USD',
                toCurrency: 'ZWG',
                rate: '999.0000',
                effectiveFrom: Carbon::now()->subDay(),
                capturedByUserId: $accountant->id,
                notes: 'Fat-fingered entry — three extra zeros.',
            ));

            app(RejectExchangeRateAction::class)->execute(new RejectExchangeRateData(
                exchangeRateId: $badRate->id,
                rejectedByUserId: $approver->id,
                reason: 'Rate is off by roughly two orders of magnitude — recapture from source.',
            ));

            return true;
        });

        $this->components->task('Approving the outstanding valid pending rate', function () use ($school, $approver): bool {
            $pending = ExchangeRate::where('school_id', $school->id)
                ->where('status', 'pending')
                ->first();

            if ($pending !== null) {
                app(ApproveExchangeRateAction::class)->execute(new ApproveExchangeRateData(
                    exchangeRateId: $pending->id,
                    approvedByUserId: $approver->id,
                ));
            }

            return true;
        });

        $this->components->task('Running an FX revaluation', function () use ($school, $accountant): bool {
            $year = $school->academicYears()->where('is_current', true)->firstOrFail();
            $term = $year->terms()->orderBy('number')->firstOrFail();

            app(RunFxRevaluationAction::class)->execute(new RunFxRevaluationData(
                schoolId: $school->id,
                academicYearId: $year->id,
                termId: $term->id,
                revaluationDate: Carbon::now(),
                performedByUserId: $accountant->id,
            ));

            return true;
        });

        $this->components->info('Currency data seeded. Run serp:seed:finance-fees next.');

        return self::SUCCESS;
    }
}
