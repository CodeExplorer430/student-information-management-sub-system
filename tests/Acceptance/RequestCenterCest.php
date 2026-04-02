<?php

declare(strict_types=1);

namespace Tests\Acceptance;

use Tests\AcceptanceTester;

final class RequestCenterCest
{
    public function studentCanSubmitRequestAndStaffCanReviewQueue(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'student@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/requests/create');
        $I->submitForm('form[action="/requests"]', [
            'request_type' => 'Profile Update',
            'priority' => 'High',
            'due_at' => '2026-04-15',
            'title' => 'Update emergency contact',
            'description' => 'Need to replace emergency contact number after family relocation.',
        ]);
        $I->see('Request Detail');
        $I->see('Update emergency contact');
        $I->see('High');

        $I->submitForm('form[action="/logout"]', []);

        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'staff@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/requests');
        $I->see('Update emergency contact');
        $I->click('Open');
        $I->see('Queue action');
        $I->submitForm('form[action*="/requests/"][action$="/transition"]', [
            'status' => 'Under Review',
            'assigned_user_id' => '2',
            'priority' => 'Urgent',
            'resolution_summary' => 'Queued for same-day operations review.',
            'remarks' => 'Operations staff started request validation.',
        ]);
        $I->see('Request updated successfully.');
        $I->see('Under Review');
        $I->submitForm('form[action*="/requests/"][action$="/notes"]', [
            'visibility' => 'student',
            'body' => 'Student-facing update added for the pending profile correction.',
        ]);
        $I->see('Request note added successfully.');
        $I->see('Student-facing update added for the pending profile correction.');

        $I->amOnPage('/notifications');
        $I->see('Notification center');
        $I->see('Request updated');
    }

    public function adminCanAccessRoleMatrixAndUserManagement(AcceptanceTester $I): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => 'admin@bcp.edu',
            'password' => 'Password123!',
        ]);

        $I->amOnPage('/admin/users');
        $I->see('User role assignment');
        $I->see('admin@bcp.edu');

        $I->amOnPage('/admin/roles');
        $I->see('Role Matrix');
        $I->see('System Administrator');
        $I->see('View request queue');

        $I->amOnPage('/reports');
        $I->see('Operational reporting and exports');
        $I->see('Export CSV');
        $I->see('Notifications');
    }
}
