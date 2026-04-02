<?php

declare(strict_types=1);

namespace App\Controllers {
    /**
     * @return resource|false
     */
    function fopen(string $filename, string $mode)
    {
        if (($GLOBALS['__sims_test_controller_fopen_false'] ?? false) === true) {
            return false;
        }

        return \fopen($filename, $mode);
    }
}

namespace Tests\Integration {

    use App\Controllers\IdCardController;
    use App\Controllers\RecordController;
    use App\Controllers\ReportController;
    use App\Controllers\RequestController;
    use App\Core\HttpResult;
    use App\Core\HttpResultException;
    use App\Repositories\RequestRepository;
    use App\Repositories\StudentRepository;
    use App\Services\FileStorageService;
    use App\Services\RequestService;
    use ReflectionMethod;
    use Tests\Support\HttpIntegrationTestCase;

    final class ControllerEdgeCoverageIntegrationTest extends HttpIntegrationTestCase
    {
        public function testRequestControllerCoversRemainingTransitionAndAttachmentPermissionBranches(): void
        {
            $student = $this->studentForEmail('student@bcp.edu');
            $this->actingAs('student@bcp.edu');
            $requestId = $this->app->get(RequestService::class)->create(
                (int) $student['id'],
                'Profile Update',
                'Edge coverage request',
                'Covers the remaining request controller branches.'
            );

            $tmpFile = tempnam(sys_get_temp_dir(), 'sims-request-edge-');
            self::assertNotFalse($tmpFile);
            file_put_contents($tmpFile, "edge attachment\n");
            $GLOBALS['__sims_test_move_uploaded_files'] = true;

            try {
                $attachment = $this->app->get(FileStorageService::class)->storeAttachment([
                    'name' => 'edge.txt',
                    'tmp_name' => $tmpFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($tmpFile),
                ]);
            } finally {
                unset($GLOBALS['__sims_test_move_uploaded_files']);
            }

            self::assertNotNull($attachment);

            $this->actingAs('staff@bcp.edu');
            $this->app->get(RequestService::class)->addNote(
                $requestId,
                'Stored edge note.',
                'student',
                $attachment
            );

            $request = $this->app->get(RequestRepository::class)->find($requestId);
            self::assertNotNull($request);
            $attachmentId = (int) ($request['attachments'][0]['id'] ?? 0);
            self::assertGreaterThan(0, $attachmentId);

            $controller = $this->app->get(RequestController::class);
            $canAccess = new ReflectionMethod(RequestController::class, 'canAccessRequest');
            $canAccess->setAccessible(true);

            $this->actingAs('faculty@bcp.edu');

            self::assertFalse($canAccess->invoke($controller, $request));
            $this->assertRedirect(
                $this->captureResult(fn () => $controller->downloadAttachment($attachmentId)),
                '/dashboard'
            );

            $this->actingAs('staff@bcp.edu');
            $_POST = [
                'status' => 'Not Valid',
                'remarks' => 'Forces the invalid transition branch.',
                'assigned_user_id' => '',
                'priority' => '',
                'due_at' => '',
                'resolution_summary' => '',
            ];

            try {
                $transition = $this->captureResult(fn () => $controller->transition($requestId));
            } finally {
                $_POST = [];
                @unlink($this->app->get(FileStorageService::class)->pathFor($attachment['stored_name']));
            }

            $this->assertRedirect($transition, '/requests/' . $requestId);
        }

        public function testIdCardControllerPrintViewRedirectsWhenNoCardExists(): void
        {
            $this->actingAs('admin@bcp.edu');

            $controller = $this->app->get(IdCardController::class);
            $student = $this->app->get(StudentRepository::class)->find(2);
            self::assertNotNull($student);

            $result = $this->captureResult(fn () => $controller->printView((int) $student['id']));

            $this->assertRedirect($result, '/id-cards');
        }

        public function testRecordExportAndReportCsvCoverFalseStreamBranches(): void
        {
            $this->actingAs('admin@bcp.edu');
            $controller = $this->app->get(RecordController::class);

            $GLOBALS['__sims_test_controller_fopen_false'] = true;

            try {
                $exportResult = $this->captureResult(fn () => $controller->export(1));

                $reportController = $this->app->get(ReportController::class);
                $toCsv = new ReflectionMethod(ReportController::class, 'toCsv');
                $toCsv->setAccessible(true);

                self::assertSame('', $toCsv->invoke($reportController, 'students', [[
                    'student_number' => 'BSI-2026-1001',
                ]]));
            } finally {
                unset($GLOBALS['__sims_test_controller_fopen_false']);
            }

            $this->assertRedirect($exportResult, '/records/1');
        }

        private function captureResult(callable $callback): HttpResult
        {
            try {
                $callback();
            } catch (HttpResultException $exception) {
                return $exception->result();
            }

            self::fail('Expected the controller to throw an HttpResultException.');
        }
    }
}
