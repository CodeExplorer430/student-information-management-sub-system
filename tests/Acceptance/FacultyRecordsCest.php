<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\AcceptanceTester;

final class FacultyRecordsCest
{
    public function facultyCanAccessAcademicRecords(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'faculty@bcp.edu',
            'password' => 'Password123!',
        ]);
        $I->amOnPage('/records');
        $I->see('Academic records viewer');
        $I->see('Secure Web Development');
        $I->amOnPage('/records/1');
        $I->see('Academic record history');
        $I->seeLink('Export CSV', '/records/1/export');
    }
}
