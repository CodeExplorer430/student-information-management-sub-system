<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\AcceptanceTester;

final class LoginCest
{
    public function dashboardIsAccessibleAfterLogin(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->see('Secure access portal');
        $I->dontSee('Default demo users');
        $I->dontSee('Password123!');
        $I->dontSee('Please sign in first.');
        $I->submitForm('form', [
            'email' => 'admin@bcp.edu',
            'password' => 'Password123!',
        ]);
        $I->see('Governance and access oversight');
        $I->see('Reports');
    }

    public function invalidCsrfTokenIsRejectedDuringLogin(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'admin@bcp.edu',
            'password' => 'Password123!',
            '_csrf' => 'invalid-token',
            '_back' => '/login',
        ]);

        $I->seeInCurrentUrl('/login');
        $I->see('Your session security token is invalid or expired. Please sign in again.');
    }
}
