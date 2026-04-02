<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\AcceptanceTester;

final class StatusTrackingCest
{
    public function adminCanFilterAndReviewStudentTimeline(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'admin@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/statuses?search=Aira&status=Approved&department=BSIT');
        $I->see('Aira Mendoza');
        $I->see('Approved');
        $I->click('Timeline');
        $I->see('Student Timeline');
        $I->see('Workflow completion');
        $I->see('Documents verified by registrar.');
    }
}
