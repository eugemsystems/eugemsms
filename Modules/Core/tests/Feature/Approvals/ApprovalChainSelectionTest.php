<?php

use Modules\Core\Domain\Support\Approvals\ApprovalChainSelector;
use Modules\Core\Domain\Support\Approvals\ConditionRuleMatcher;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\ApprovalChain;
use Modules\Core\Models\School;
use Modules\Core\Tests\Fixtures\TestApprovable;

it('selects the highest-priority chain whose condition rules match (BR-CORE-07-001/AC-CORE-07-001)', function (): void {
    $school = School::factory()->create();

    $bursarChain = ApprovalChain::factory()->for($school)->create([
        'approvable_type' => 'test_approvable',
        'name' => 'Bursar chain',
        'priority' => 1,
        'is_default' => false,
        'condition_rules' => [['field' => 'amount_minor', 'operator' => '<', 'value' => 50000]],
    ]);
    $headChain = ApprovalChain::factory()->for($school)->create([
        'approvable_type' => 'test_approvable',
        'name' => 'Head chain',
        'priority' => 2,
        'is_default' => false,
        'condition_rules' => [['field' => 'amount_minor', 'operator' => '>=', 'value' => 50000]],
    ]);

    $order = new TestApprovable(['school_id' => $school->id, 'name' => 'PO-1']);
    $order->testAmount = Money::of(75000, Currency::USD);

    $selector = new ApprovalChainSelector(new ConditionRuleMatcher);
    $selected = $selector->select($school->id, $order);

    expect($selected->id)->toBe($headChain->id);

    $small = new TestApprovable(['school_id' => $school->id, 'name' => 'PO-2']);
    $small->testAmount = Money::of(1000, Currency::USD);
    expect($selector->select($school->id, $small)->id)->toBe($bursarChain->id);
});

it('falls back to the default chain when nothing else matches', function (): void {
    $school = School::factory()->create();

    ApprovalChain::factory()->for($school)->create([
        'approvable_type' => 'test_approvable',
        'is_default' => false,
        'condition_rules' => [['field' => 'amount_minor', 'operator' => '>', 'value' => 1_000_000]],
    ]);
    $default = ApprovalChain::factory()->for($school)->create([
        'approvable_type' => 'test_approvable',
        'is_default' => true,
        'condition_rules' => null,
    ]);

    $order = new TestApprovable(['school_id' => $school->id, 'name' => 'PO-3']);
    $order->testAmount = Money::of(100, Currency::USD);

    $selected = (new ApprovalChainSelector(new ConditionRuleMatcher))->select($school->id, $order);

    expect($selected->id)->toBe($default->id);
});
