<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\AcceptanceTester;

final class AuthorizationCest
{
    public function guestIsRedirectedToLoginForProtectedRoutes(AcceptanceTester $I): void
    {
        $I->amOnPage('/students/create');
        $I->seeInCurrentUrl('/login');
        $I->see('Please sign in first.');
    }

    public function facultyCannotAccessStudentRegistration(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'faculty@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/students/create');
        $I->see('You are not authorized to access this resource.');
        $I->see('Dashboard');
    }

    public function studentCannotAccessAnotherStudentsProtectedResources(AcceptanceTester $I): void
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
        $I->see('Student ID preview');
        $I->submitForm('form[action="/logout"]', []);

        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'student@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/students/2');
        $I->see('You can only access your own profile.');

        $I->amOnPage('/records/2');
        $I->see('You can only view your own records.');

        $I->amOnPage('/statuses/2');
        $I->see('You can only view your own status timeline.');

        $I->amOnPage('/id-cards/2/print');
        $I->see('You can only preview your own ID.');
    }

    public function facultyCannotAccessWorkflowOrIdModules(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'faculty@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/statuses');
        $I->see('You are not authorized to access this resource.');

        $I->amOnPage('/id-cards');
        $I->see('You are not authorized to access this resource.');
    }
}
