<?php

declare(strict_types=1);

namespace Tests\DeploymentSmoke;

use PHPUnit\Framework\Assert;
use Tests\AcceptanceTester;

final class DeploymentSmokeCest
{
    public function healthEndpointsRemainHealthy(AcceptanceTester $I): void
    {
        $I->amOnPage('/health/live');
        $I->seeResponseCodeIsSuccessful();
        $I->see('"status":"pass"');

        $I->amOnPage('/health/ready');
        $I->seeResponseCodeIsSuccessful();
        $I->see('"status":"pass"');
        $I->see('"checks"');
    }

    public function studentCanAccessOwnWorkspaceAndIsBlockedFromAdminArea(AcceptanceTester $I): void
    {
        $this->login(
            $I,
            $this->requiredEnv('DEPLOY_SMOKE_STUDENT_EMAIL'),
            $this->requiredEnv('DEPLOY_SMOKE_STUDENT_PASSWORD')
        );

        $I->see('Self-service student dashboard');

        $profilePath = $this->ownProfilePath($I);
        $I->amOnPage($profilePath);
        $I->seeInCurrentUrl('/students/');

        $I->amOnPage('/admin/users');
        $I->see('You are not authorized to access this resource.');
    }

    public function adminCanOpenDiagnosticsAndVerifyGeneratedId(AcceptanceTester $I): void
    {
        $studentEmail = $this->requiredEnv('DEPLOY_SMOKE_STUDENT_EMAIL');

        $this->login(
            $I,
            $this->requiredEnv('DEPLOY_SMOKE_ADMIN_EMAIL'),
            $this->requiredEnv('DEPLOY_SMOKE_ADMIN_PASSWORD')
        );

        $I->see('Governance and access oversight');

        $I->amOnPage('/admin/diagnostics');
        $I->see('Current readiness');
        $I->see('Recent application events');

        $studentId = $this->studentIdForEmail($I, $studentEmail);

        $I->amOnPage('/id-cards');
        $I->submitForm('form[action="/id-cards/generate"]', [
            'student_id' => (string) $studentId,
        ]);
        $I->see('Student ID preview');
        $I->seeElement('body.page-id-card-preview');
        $I->seeElement('.id-preview-primary');
        $I->seeElement('button[data-print-trigger]');
        $I->seeElement('.generated-card-shell img');
        $I->see('Download PNG');

        $I->amOnPage('/id-cards/' . $studentId . '/verify');
        $I->see('Verified Bestlink College student record');
        $I->see('Enrollment');
    }

    private function login(AcceptanceTester $I, string $email, string $password): void
    {
        $I->amOnPage('/login');
        $I->submitForm('form', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    private function ownProfilePath(AcceptanceTester $I): string
    {
        $profilePath = $I->grabAttributeFrom(
            '//a[contains(@href, "/students/") and contains(normalize-space(.), "View Profile")]',
            'href'
        );

        Assert::assertIsString($profilePath);
        Assert::assertMatchesRegularExpression('~^/students/\d+$~', $profilePath);

        return $profilePath;
    }

    private function studentIdForEmail(AcceptanceTester $I, string $email): int
    {
        $I->amOnPage('/students?search=' . rawurlencode($email));
        $I->see($email);

        $profilePath = $I->grabAttributeFrom(
            sprintf('//tr[td[contains(normalize-space(.), "%s")]]//a[contains(@href, "/students/")]', $email),
            'href'
        );

        Assert::assertIsString($profilePath);
        Assert::assertMatchesRegularExpression('~^/students/\d+$~', $profilePath);
        preg_match('~^/students/(\d+)$~', $profilePath, $matches);
        $studentId = $matches[1] ?? '';

        Assert::assertNotSame('', $studentId);

        return (int) $studentId;
    }

    private function requiredEnv(string $name): string
    {
        $value = getenv($name);

        Assert::assertIsString($value, sprintf('Expected [%s] to be defined for deployment smoke.', $name));
        Assert::assertNotSame('', $value, sprintf('Expected [%s] to be non-empty for deployment smoke.', $name));

        return $value;
    }
}
