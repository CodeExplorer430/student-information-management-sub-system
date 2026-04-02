<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Session;
use App\Repositories\NotificationRepository;
use App\Repositories\RequestRepository;
use App\Services\RequestService;
use InvalidArgumentException;
use Tests\Support\IntegrationTestCase;

final class RequestServiceIntegrationTest extends IntegrationTestCase
{
    public function testStudentCanCreateAndStaffCanTransitionRequest(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 3);

        $service = $this->app->get(RequestService::class);
        $requestId = $service->create(
            1,
            'Profile Update',
            'Correct guardian contact',
            'Student needs to update the guardian contact number after an emergency change.'
        );

        $created = $this->app->get(RequestRepository::class)->find($requestId);

        self::assertNotNull($created);
        self::assertSame('Pending', $created['status']);
        self::assertCount(1, $created['history']);

        $session->set('auth.user_id', 2);
        $service->transition($requestId, 'Under Review', 'Operations staff is checking supporting records.', 2);

        $updated = $this->app->get(RequestRepository::class)->find($requestId);

        self::assertNotNull($updated);
        self::assertSame('Under Review', $updated['status']);
        self::assertSame(2, (int) $updated['assigned_user_id']);
        self::assertCount(2, $updated['history']);
        self::assertSame('Under Review', $updated['history'][0]['status']);
    }

    public function testStudentVisibleNoteCreatesNotificationAndAttachmentMetadata(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 3);

        $service = $this->app->get(RequestService::class);
        $requestId = $service->create(
            1,
            'ID Issuance Support',
            'Need updated ID release estimate',
            'Student is asking for a release estimate because of internship onboarding.',
            'High',
            '2026-04-10'
        );

        $session->set('auth.user_id', 2);
        $service->addNote($requestId, 'Registrar queued the ID request for same-week release.', 'student', [
            'stored_name' => 'request-attachment-fixture.pdf',
            'original_name' => 'release-note.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $request = $this->app->get(RequestRepository::class)->find($requestId);
        $notifications = $this->app->get(NotificationRepository::class)->forUser(3);

        self::assertNotNull($request);
        self::assertSame('High', $request['priority']);
        self::assertNotEmpty($request['notes']);
        self::assertSame('student', $request['notes'][0]['visibility']);
        self::assertNotEmpty($request['attachments']);
        self::assertSame('release-note.pdf', $request['attachments'][0]['original_name']);
        self::assertContains('ID Issuance Support', $this->app->get(RequestRepository::class)->requestTypes());
        self::assertNotNull($this->app->get(RequestRepository::class)->findAttachment((int) $request['attachments'][0]['id']));
        self::assertNotEmpty($notifications);
    }

    public function testRequestServiceRejectsInvalidInputsAndCoversPriorityDefaults(): void
    {
        $session = $this->app->get(Session::class);
        $session->set('auth.user_id', 2);
        $service = $this->app->get(RequestService::class);

        try {
            $service->create(1, 'Invalid Type', 'Broken', 'Broken');
            self::fail('Expected invalid type failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid request type.', $exception->getMessage());
        }

        try {
            $service->create(1, 'Profile Update', 'Broken', 'Broken', 'Immediate');
            self::fail('Expected invalid priority failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid request priority.', $exception->getMessage());
        }

        try {
            $service->create(1, 'Profile Update', '', '');
            self::fail('Expected missing title failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Title and description are required.', $exception->getMessage());
        }

        $urgentId = $service->create(
            1,
            'Profile Update',
            'Urgent request',
            'Urgent request description',
            'Urgent'
        );
        $highId = $service->create(
            1,
            'Profile Update',
            'High request',
            'High request description',
            'High'
        );
        $lowId = $service->create(
            1,
            'Profile Update',
            'Low request',
            'Low request description',
            'Low'
        );

        $repository = $this->app->get(RequestRepository::class);
        $urgent = $repository->find($urgentId);
        $high = $repository->find($highId);
        $low = $repository->find($lowId);

        self::assertNotNull($urgent);
        self::assertNotNull($high);
        self::assertNotNull($low);
        self::assertSame(date('Y-m-d', strtotime('+1 day')), substr((string) $urgent['due_at'], 0, 10));
        self::assertSame(date('Y-m-d', strtotime('+2 day')), substr((string) $high['due_at'], 0, 10));
        self::assertSame(date('Y-m-d', strtotime('+7 day')), substr((string) $low['due_at'], 0, 10));

        try {
            $service->transition(9999, 'Pending', 'Missing request');
            self::fail('Expected missing request transition failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Request not found.', $exception->getMessage());
        }

        try {
            $service->transition($urgentId, 'Invalid', 'Bad status');
            self::fail('Expected invalid transition status failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid request status.', $exception->getMessage());
        }

        try {
            $service->addNote($urgentId, '', 'student');
            self::fail('Expected empty note failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Note body is required.', $exception->getMessage());
        }

        try {
            $service->addNote($urgentId, 'Invalid visibility', 'hidden');
            self::fail('Expected invalid visibility failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid note visibility.', $exception->getMessage());
        }

        try {
            $service->addNote(9999, 'Missing request', 'student');
            self::fail('Expected missing request note failure.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Request not found.', $exception->getMessage());
        }
    }
}
