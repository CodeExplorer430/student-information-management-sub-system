<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\AcceptanceTester;

final class StudentProfileCest
{
    public function adminCanRegisterStudentWithPhotoAndSeeDefaultStatuses(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'admin@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/students/create');
        $I->attachFile('input[name="photo"]', 'student-photo.jpg');
        $I->submitForm('form[action="/students"]', [
            'first_name' => 'Nadia',
            'middle_name' => 'Lopez',
            'last_name' => 'Cruz',
            'birthdate' => '2005-07-01',
            'program' => 'BS Information Technology',
            'year_level' => '2',
            'email' => 'nadia.cruz@student.bcp.edu',
            'phone' => '09170000999',
            'address' => 'Plaridel, Bulacan',
            'guardian_name' => 'Liza Cruz',
            'guardian_contact' => '09170000123',
            'department' => 'BSIT',
        ]);

        $I->see('Student profile registered successfully.');
        $I->see('Nadia Cruz');
        $I->see('Pending');
        $I->see('Active');
        $I->see('On file');
    }

    public function studentCanUpdateOwnProfileAndSeeAuditEntry(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'student@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/students/1/edit');
        $I->submitForm('form[action="/students/1/update"]', [
            'first_name' => 'Aira',
            'middle_name' => 'Lopez',
            'last_name' => 'Mendoza',
            'birthdate' => '2005-03-14',
            'program' => 'BS Information Technology',
            'year_level' => '3',
            'email' => 'student@bcp.edu',
            'phone' => '09998887777',
            'address' => 'Updated Malolos, Bulacan',
            'guardian_name' => 'Marites Mendoza',
            'guardian_contact' => '09170000011',
            'department' => 'BSIT',
        ]);

        $I->see('Student profile updated successfully.');
        $I->see('09998887777');
        $I->see('Updated');
    }

    public function invalidPhotoUploadIsRejectedSafely(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'admin@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/students/create');
        $I->attachFile('input[name="photo"]', 'not-an-image.txt');
        $I->submitForm('form[action="/students"]', [
            'first_name' => 'Nadia',
            'middle_name' => 'Lopez',
            'last_name' => 'Cruz',
            'birthdate' => '2005-07-01',
            'program' => 'BS Information Technology',
            'year_level' => '2',
            'email' => 'nadia.invalid-upload@student.bcp.edu',
            'phone' => '09170000999',
            'address' => 'Plaridel, Bulacan',
            'guardian_name' => 'Liza Cruz',
            'guardian_contact' => '09170000123',
            'department' => 'BSIT',
        ]);

        $I->see('Only JPG, PNG, and WEBP images are allowed.');
        $I->dontSee('Student profile registered successfully.');
    }
}
