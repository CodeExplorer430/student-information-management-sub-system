<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\AcceptanceTester;

final class IdVerificationCest
{
    public function generatedIdResolvesToVerificationPage(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'admin@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/id-cards');
        $I->submitForm('form[action="/id-cards/generate"]', [
            'student_id' => '1',
        ]);
        $I->see('Student ID preview');
        $I->amOnPage('/id-cards/1/verify');
        $I->see('Verified Bestlink College student record');
        $I->see('Active');
    }
}
