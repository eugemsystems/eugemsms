<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Sport\Domain\DataObjects\RecordFixtureResultData;
use Modules\Sport\Models\Fixture;

/**
 * ACT-RecordFixtureResult (Book H2 OPS-07 §2).
 */
final class RecordFixtureResultAction extends Action
{
    public function execute(RecordFixtureResultData $data): Fixture
    {
        $fixture = Fixture::findOrFail($data->fixtureId);

        return $this->transaction(fn (): Fixture => tap($fixture)->update([
            'result' => $data->result,
            'score_for' => $data->scoreFor,
            'score_against' => $data->scoreAgainst,
            'match_report' => $data->matchReport,
            'status' => 'completed',
        ]));
    }
}
