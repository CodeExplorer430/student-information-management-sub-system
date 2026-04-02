<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\AcceptanceTester;

final class IdCardDeliveryCest
{
    public function adminCanPreviewAndDownloadGeneratedId(AcceptanceTester $I): void
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
        $I->see('Bestlink College of the Philippines');
        $I->see('Ready for record checks.');

        $I->amOnPage('/id-cards');
        $I->see('Generated');
        $I->seeLink('Preview', '/id-cards/1/print');
        $I->seeLink('Download', '/id-cards/1/download');
        $I->seeLink('Verify', '/id-cards/1/verify');

        $I->amOnPage('/id-cards/1/verify');
        $I->see('Verified Bestlink College student record');

        $I->amOnPage('/id-cards');
        $I->see('Generated');

        $I->amOnPage('/id-cards/1/download');
        $I->seeResponseCodeIsSuccessful();
    }

    public function missingGeneratedIdFileIsHandledSafely(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'admin@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/id-cards');
        $I->submitForm('form[action="/id-cards/generate"]', [
            'student_id' => '2',
        ]);

        @unlink(dirname(__DIR__, 2) . '/storage/app/public/id-cards/student-id-2.png');

        $I->amOnPage('/id-cards/2/print');
        $I->see('The generated ID file is no longer available.');

        $I->amOnPage('/id-cards/2/download');
        $I->see('The generated ID file is no longer available.');
    }
}
