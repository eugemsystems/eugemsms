<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

// Each module's tests/{Feature,Unit} directories are bound here explicitly
// as they're added — see Modules/Core/tests for the Book A Part 1 suite.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', '../Modules/Core/tests/Feature', '../Modules/Finance/tests/Feature', '../Modules/People/tests/Feature', '../Modules/Academic/tests/Feature', '../Modules/Payroll/tests/Feature', '../Modules/Boarding/tests/Feature', '../Modules/Welfare/tests/Feature', '../Modules/Stores/tests/Feature', '../Modules/Operations/tests/Feature', '../Modules/Transport/tests/Feature', '../Modules/Utilities/tests/Feature', '../Modules/Farm/tests/Feature', '../Modules/Facilities/tests/Feature', '../Modules/Security/tests/Feature', '../Modules/Sport/tests/Feature', '../Modules/Fiscal/tests/Feature', '../Modules/Wallet/tests/Feature', '../Modules/Reporting/tests/Feature', '../Modules/Compliance/tests/Feature', '../Modules/Comms/tests/Feature');

pest()->extend(TestCase::class)
    ->in('../Modules/Core/tests/Unit', '../Modules/Finance/tests/Unit', '../Modules/People/tests/Unit', '../Modules/Academic/tests/Unit', '../Modules/Payroll/tests/Unit', '../Modules/Boarding/tests/Unit', '../Modules/Welfare/tests/Unit', '../Modules/Stores/tests/Unit', '../Modules/Operations/tests/Unit', '../Modules/Transport/tests/Unit', '../Modules/Utilities/tests/Unit', '../Modules/Farm/tests/Unit', '../Modules/Facilities/tests/Unit', '../Modules/Security/tests/Unit', '../Modules/Sport/tests/Unit', '../Modules/Fiscal/tests/Unit', '../Modules/Wallet/tests/Unit', '../Modules/Reporting/tests/Unit', '../Modules/Compliance/tests/Unit', '../Modules/Comms/tests/Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
