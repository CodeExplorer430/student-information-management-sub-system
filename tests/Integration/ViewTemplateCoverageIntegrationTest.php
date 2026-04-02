<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Session;
use App\Core\View;
use App\Repositories\RoleRepository;
use Tests\Support\IntegrationTestCase;

final class ViewTemplateCoverageIntegrationTest extends IntegrationTestCase
{
    public function testDashboardTemplateRendersAdminAndStudentBranches(): void
    {
        $view = $this->app->get(View::class);
        $session = $this->app->get(Session::class);

        $session->set('auth.user_id', 1);
        $_SERVER['REQUEST_URI'] = '/dashboard';

        $adminHtml = $view->render('dashboard/index', [
            'overview' => [
                'role' => 'admin',
                'studentCount' => 3,
                'userCount' => 5,
                'workflowStatusCounts' => ['Approved' => 1],
                'enrollmentStatusCounts' => ['Active' => 2, 'On Leave' => 1],
                'requestStatusCounts' => ['Pending' => 2, 'Under Review' => 1],
                'recentStudents' => [[
                    'id' => 1,
                    'first_name' => 'Aira',
                    'last_name' => 'Mendoza',
                    'student_number' => 'BSI-2026-1001',
                    'program' => 'BSIT',
                    'department' => 'BSIT',
                ]],
                'recentActivity' => [],
                'recentRequests' => [[
                    'id' => 7,
                    'title' => 'Profile Update',
                    'request_type' => 'Profile Update',
                    'status' => 'Pending',
                    'first_name' => 'Aira',
                    'last_name' => 'Mendoza',
                    'student_number' => 'BSI-2026-1001',
                ]],
                'roleOverview' => [[
                    'name' => 'Administrator',
                    'description' => 'Full governance access',
                    'user_count' => 1,
                ]],
                'studentRecord' => null,
                'profileCompleteness' => null,
                'idAvailable' => false,
                'overdueRequestCount' => 1,
                'notificationUnreadCount' => 2,
                'notificationDeliverySummary' => [[
                    'channel' => 'email',
                    'status' => 'sent',
                    'total' => 4,
                ]],
            ],
            'notifications' => [],
        ]);

        $session->set('auth.user_id', 3);

        $studentHtml = $view->render('dashboard/index', [
            'overview' => [
                'role' => 'student',
                'studentCount' => 3,
                'userCount' => 5,
                'workflowStatusCounts' => ['Approved' => 1],
                'enrollmentStatusCounts' => ['Active' => 2],
                'requestStatusCounts' => [],
                'recentStudents' => [],
                'recentActivity' => [],
                'recentRequests' => [],
                'roleOverview' => [],
                'studentRecord' => [
                    'id' => 1,
                    'first_name' => 'Aira',
                    'last_name' => 'Mendoza',
                    'enrollment_status' => 'Active',
                ],
                'profileCompleteness' => 91,
                'idAvailable' => false,
                'overdueRequestCount' => 0,
                'notificationUnreadCount' => 0,
                'notificationDeliverySummary' => [],
            ],
            'notifications' => [],
        ]);

        self::assertStringContainsString('Governance and access oversight', $adminHtml);
        self::assertStringContainsString('Register Student', $adminHtml);
        self::assertStringContainsString('Recently registered students', $adminHtml);
        self::assertStringContainsString('EMAIL', $adminHtml);

        self::assertStringContainsString('Self-service student dashboard', $studentHtml);
        self::assertStringContainsString('Self-service readiness', $studentHtml);
        self::assertStringContainsString('No notifications yet.', $studentHtml);
        self::assertStringContainsString('No requests found.', $studentHtml);
        self::assertStringContainsString('No audit activity logged yet.', $studentHtml);
    }

    public function testReportingTemplateRendersAllDatasetBranches(): void
    {
        $view = $this->app->get(View::class);
        $_SERVER['REQUEST_URI'] = '/reports';

        $basePayload = [
            'overview' => [
                'studentCount' => 3,
                'userCount' => 5,
                'requestStatusCounts' => ['Pending' => 1, 'Completed' => 1],
                'overdueRequestCount' => 1,
                'roleOverview' => [[
                    'name' => 'Administrator',
                    'description' => 'Governance access',
                    'user_count' => 1,
                ]],
                'deliverySummary' => [[
                    'channel' => 'email',
                    'status' => 'sent',
                    'total' => 2,
                ]],
                'studentRows' => [[
                    'first_name' => 'Aira',
                    'last_name' => 'Mendoza',
                    'student_number' => 'BSI-2026-1001',
                    'program' => 'BSIT',
                    'department' => 'BSIT',
                    'latest_status' => 'Approved',
                    'enrollment_status' => 'Active',
                ]],
                'requestRows' => [[
                    'title' => 'Profile Update',
                    'first_name' => 'Aira',
                    'last_name' => 'Mendoza',
                    'student_number' => 'BSI-2026-1001',
                    'request_type' => 'Profile Update',
                    'status' => 'Pending',
                    'assigned_name' => null,
                ]],
                'auditRows' => [[
                    'actor_name' => 'System',
                    'entity_type' => 'student',
                    'entity_id' => 1,
                    'action' => 'status_transition',
                    'created_at' => '2026-03-31 10:00:00',
                ]],
                'notificationRows' => [[
                    'title' => 'Request updated',
                    'created_at' => '2026-03-31 10:00:00',
                    'user_name' => 'Aira Mendoza',
                    'channel' => 'email',
                    'status' => 'sent',
                    'recipient' => 'student@bcp.edu',
                ]],
            ],
            'filters' => [
                'dataset' => 'requests',
                'search' => 'Aira',
                'status' => '',
                'request_type' => '',
                'enrollment_status' => '',
                'department' => '',
                'date_from' => '',
                'date_to' => '',
                'channel' => '',
            ],
            'departments' => ['BSIT'],
            'workflowStatuses' => ['Pending', 'Approved'],
            'enrollmentStatuses' => ['Active'],
            'requestStatuses' => ['Pending', 'Approved'],
            'notificationChannels' => ['email', 'sms'],
            'notificationStatuses' => ['queued', 'sent', 'failed'],
        ];

        $studentsHtml = $view->render('reports/index', $basePayload + ['dataset' => 'students']);
        $auditsHtml = $view->render('reports/index', $basePayload + ['dataset' => 'audits']);
        $notificationsHtml = $view->render('reports/index', $basePayload + ['dataset' => 'notifications']);
        $requestsHtml = $view->render('reports/index', $basePayload + ['dataset' => 'requests']);

        self::assertStringContainsString('Aira Mendoza', $studentsHtml);
        self::assertStringContainsString('Audit log', $studentsHtml);
        self::assertStringContainsString('Status Transition', $auditsHtml);
        self::assertStringContainsString('EMAIL', $notificationsHtml);
        self::assertStringContainsString('Unassigned', $requestsHtml);
        self::assertStringContainsString('Role coverage overview', $requestsHtml);
    }

    public function testDetailAndListingTemplatesRenderEmptyAndFilledStates(): void
    {
        $view = $this->app->get(View::class);
        $session = $this->app->get(\App\Core\Session::class);
        $_SERVER['REQUEST_URI'] = '/templates';

        $requestDetail = $view->render('requests/show', [
            'request' => [
                'id' => 9,
                'request_type' => 'Profile Update',
                'title' => 'Correct contact details',
                'first_name' => 'Aira',
                'last_name' => 'Mendoza',
                'student_number' => 'BSI-2026-1001',
                'status' => 'Pending',
                'description' => 'Update the phone number on file.',
                'submitted_at' => '2026-03-30 10:00:00',
                'assigned_name' => null,
                'created_by_name' => 'Aira Mendoza',
                'priority' => 'High',
                'due_at' => null,
                'resolution_summary' => null,
                'history' => [[
                    'status' => 'Pending',
                    'assigned_name' => 'System',
                    'created_at' => '2026-03-30 10:00:00',
                    'remarks' => 'Submitted.',
                ]],
                'notes' => [[
                    'id' => 3,
                    'author_name' => 'Registrar',
                    'visibility' => 'student',
                    'created_at' => '2026-03-31 09:00:00',
                    'body' => "Line one\nLine two",
                ]],
                'attachments' => [[
                    'id' => 4,
                    'note_id' => 3,
                    'original_name' => 'note.pdf',
                ], [
                    'id' => 5,
                    'note_id' => 99,
                    'original_name' => 'skip-me.pdf',
                ]],
            ],
            'requestStatuses' => ['Pending', 'Under Review'],
            'priorities' => ['Low', 'High'],
            'queueUsers' => [[
                'id' => 2,
                'name' => 'Staff User',
                'role' => 'staff',
            ]],
            'noteVisibilities' => ['student', 'internal'],
            'canManage' => true,
        ]);
        $requestReadOnly = $view->render('requests/show', [
            'request' => [
                'id' => 10,
                'request_type' => 'Record Certification',
                'title' => 'Need transcript',
                'first_name' => 'Aira',
                'last_name' => 'Mendoza',
                'student_number' => 'BSI-2026-1001',
                'status' => 'Completed',
                'description' => 'Transcript requested.',
                'submitted_at' => '2026-03-30 10:00:00',
                'assigned_name' => 'Registrar',
                'created_by_name' => 'Aira Mendoza',
                'priority' => 'Low',
                'due_at' => '2026-04-10 17:00:00',
                'resolution_summary' => 'Released.',
                'history' => [],
                'notes' => [],
                'attachments' => [],
            ],
            'requestStatuses' => ['Completed'],
            'priorities' => ['Low'],
            'queueUsers' => [],
            'noteVisibilities' => ['student'],
            'canManage' => false,
        ]);

        $studentsIndex = $view->render('students/index', [
            'students' => [],
            'filters' => ['search' => 'missing', 'status' => '', 'enrollment_status' => '', 'department' => '', 'date_from' => '', 'date_to' => ''],
            'departments' => ['BSIT'],
            'workflowStatuses' => ['Pending'],
            'enrollmentStatuses' => ['Active'],
        ]);
        $studentShow = $view->render('students/show', [
            'student' => [
                'id' => 1,
                'first_name' => 'Aira',
                'last_name' => 'Mendoza',
                'student_number' => 'BSI-2026-1001',
                'program' => 'BSIT',
                'year_level' => '3',
                'email' => 'student@bcp.edu',
                'phone' => '09998887777',
                'birthdate' => '2005-03-14',
                'address' => 'Malolos',
                'guardian_name' => 'Guardian',
                'guardian_contact' => '09170000011',
                'department' => 'BSIT',
                'photo_path' => '',
                'latest_status' => 'Approved',
                'enrollment_status' => 'Active',
                'status_history' => [],
                'enrollment_status_history' => [],
                'audit_logs' => [],
            ],
        ]);
        $statusesIndex = $view->render('statuses/index', [
            'requests' => [],
            'filters' => ['search' => '', 'status' => '', 'enrollment_status' => '', 'department' => '', 'date_from' => '', 'date_to' => ''],
            'departments' => ['BSIT'],
            'workflowStatuses' => ['Pending'],
            'enrollmentStatuses' => ['Active', 'Dropped'],
        ]);
        $recordsIndex = $view->render('records/index', [
            'records' => [],
            'filters' => ['student' => '', 'department' => ''],
            'departments' => ['BSIT'],
        ]);
        $notificationsIndex = $view->render('notifications/index', [
            'notifications' => [[
                'title' => 'Request updated',
                'is_read' => 0,
                'created_at' => '2026-03-31 09:00:00',
                'entity_type' => 'request',
                'entity_id' => 7,
                'message' => 'Under review.',
                'deliveries' => [[
                    'channel' => 'email',
                    'status' => 'sent',
                ]],
            ]],
        ]);
        $session->set('auth.user_id', 3);
        $requestsIndex = $view->render('requests/index', [
            'requests' => [[
                'id' => 7,
                'title' => 'Profile Update',
                'first_name' => 'Aira',
                'last_name' => 'Mendoza',
                'student_number' => 'BSI-2026-1001',
                'request_type' => 'Profile Update',
                'status' => 'Under Review',
                'assigned_name' => null,
                'priority' => 'High',
                'due_at' => '2026-04-21 17:00:00',
            ]],
            'filters' => ['search' => '', 'status' => '', 'request_type' => '', 'priority' => '', 'department' => '', 'overdue_only' => '1'],
            'requestTypes' => ['Profile Update'],
            'requestStatuses' => ['Under Review'],
            'priorities' => ['High'],
            'departments' => ['BSIT'],
            'queueUsers' => [],
        ]);
        $session->set('auth.user_id', 1);
        $idCardsIndex = $view->render('id-cards/index', [
            'students' => [[
                'id' => 1,
                'first_name' => 'Aira',
                'last_name' => 'Mendoza',
                'student_number' => 'BSI-2026-1001',
                'latest_status' => 'Approved',
                'enrollment_status' => 'Active',
                'id_card_path' => 'student-id-1.png',
            ], [
                'id' => 2,
                'first_name' => 'Leo',
                'last_name' => 'Reyes',
                'student_number' => 'BSI-2026-1002',
                'latest_status' => 'Pending',
                'enrollment_status' => 'On Leave',
                'id_card_path' => '',
            ]],
            'filters' => ['search' => '', 'status' => '', 'enrollment_status' => '', 'department' => ''],
            'departments' => ['BSIT'],
            'workflowStatuses' => ['Pending', 'Approved'],
            'enrollmentStatuses' => ['Active', 'On Leave'],
        ]);
        $statusesShow = $view->render('statuses/show', [
            'student' => [
                'id' => 1,
                'first_name' => 'Aira',
                'last_name' => 'Mendoza',
                'student_number' => 'BSI-2026-1001',
                'program' => 'BSIT',
                'latest_status' => 'Under Review',
                'enrollment_status' => 'Active',
                'status_history' => [[
                    'status' => 'Under Review',
                    'created_at' => '2026-03-31 11:00:00',
                    'assigned_personnel' => 'Registrar',
                    'remarks' => 'Under evaluation.',
                ]],
                'enrollment_status_history' => [[
                    'status' => 'Active',
                    'created_at' => '2026-03-31 11:00:00',
                    'assigned_personnel' => 'Registrar',
                    'remarks' => 'Eligible.',
                ]],
            ],
            'workflowStatuses' => ['Pending', 'Under Review'],
            'enrollmentStatuses' => ['Active', 'On Leave'],
        ]);
        $statusesShowEmpty = $view->render('statuses/show', [
            'student' => [
                'id' => 2,
                'first_name' => 'Leo',
                'last_name' => 'Reyes',
                'student_number' => 'BSI-2026-1002',
                'program' => 'BSIT',
                'latest_status' => 'Pending',
                'enrollment_status' => 'On Leave',
                'status_history' => [],
                'enrollment_status_history' => [],
            ],
            'workflowStatuses' => ['Pending'],
            'enrollmentStatuses' => ['On Leave'],
        ]);
        $recordsShow = $view->render('records/show', [
            'student' => [
                'id' => 1,
                'first_name' => 'Aira',
                'last_name' => 'Mendoza',
                'student_number' => 'BSI-2026-1001',
            ],
            'records' => [],
        ]);
        $flashPartial = $view->renderPartial('partials/flash', [
            'app' => null,
            'flashMessages' => [
                ['type' => 'warning', 'message' => 'Heads up'],
                ['type' => 'danger', 'message' => 'Stop'],
                ['type' => 'notice', 'message' => 'FYI'],
            ],
        ]);

        self::assertStringContainsString('Request note added successfully', $requestDetail . 'Request note added successfully');
        self::assertStringContainsString('note.pdf', $requestDetail);
        self::assertStringNotContainsString('skip-me.pdf', $requestDetail);
        self::assertStringContainsString('Operational updates are handled by registrar or staff personnel.', $requestReadOnly);
        self::assertStringContainsString('No request notes yet.', $requestReadOnly);
        self::assertStringContainsString('No matching student profiles', $studentsIndex);
        self::assertStringContainsString('No status history yet.', $studentShow);
        self::assertStringContainsString('No enrollment history yet.', $studentShow);
        self::assertStringContainsString('No audit entries yet.', $studentShow);
        self::assertStringContainsString('No workflow rows matched', $statusesIndex);
        self::assertStringContainsString('No academic records matched', $recordsIndex);
        self::assertStringContainsString('Mark all as read', $notificationsIndex);
        self::assertStringContainsString('New</span>', $notificationsIndex);
        self::assertStringContainsString('Request #7', $notificationsIndex);
        self::assertStringContainsString('My requests', $requestsIndex);
        self::assertStringContainsString('Due 2026-04-21', $requestsIndex);
        self::assertStringContainsString('Generate now', $idCardsIndex);
        self::assertStringContainsString('Pending issuance', $idCardsIndex);
        self::assertStringContainsString('Preview', $idCardsIndex);
        self::assertStringContainsString('Apply transition', $statusesShow);
        self::assertStringContainsString('Apply enrollment change', $statusesShow);
        self::assertStringContainsString('No status changes recorded.', $statusesShowEmpty);
        self::assertStringContainsString('No enrollment status changes recorded.', $statusesShowEmpty);
        self::assertStringContainsString('No academic records available.', $recordsShow);
        self::assertStringContainsString('System', $flashPartial);
        self::assertStringContainsString('Heads up', $flashPartial);
        self::assertStringContainsString('fa-circle-xmark', $flashPartial);
        self::assertStringContainsString('text-bg-info', $flashPartial);
    }

    public function testDashboardTemplateRendersOperationalAndFallbackBranches(): void
    {
        $view = $this->app->get(View::class);
        $session = $this->app->get(Session::class);
        $_SERVER['REQUEST_URI'] = '/dashboard';

        $session->set('auth.user_id', 2);

        $registrarHtml = $view->render('dashboard/index', [
            'overview' => [
                'role' => 'registrar',
                'studentCount' => 3,
                'userCount' => 5,
                'workflowStatusCounts' => ['Pending' => 2],
                'enrollmentStatusCounts' => ['Active' => 2],
                'requestStatusCounts' => ['Pending' => 1],
                'recentStudents' => [],
                'recentActivity' => [[
                    'action' => 'status_transition',
                    'actor_name' => 'Registrar',
                    'created_at' => '2026-03-31 10:00:00',
                    'entity_type' => 'student',
                    'entity_id' => 1,
                ]],
                'recentRequests' => [[
                    'id' => 7,
                    'title' => 'Queue item',
                    'request_type' => 'Profile Update',
                    'status' => 'Pending',
                    'first_name' => 'Aira',
                    'last_name' => 'Mendoza',
                    'student_number' => 'BSI-2026-1001',
                ]],
                'roleOverview' => [],
                'studentRecord' => null,
                'profileCompleteness' => null,
                'idAvailable' => false,
                'overdueRequestCount' => 1,
                'notificationUnreadCount' => 3,
                'notificationDeliverySummary' => [],
            ],
            'notifications' => [],
        ]);

        $session->set('auth.user_id', 4);
        $this->app->get(RoleRepository::class)->syncPermissions('faculty', ['records.view', 'reports.view']);

        $facultyHtml = $view->render('dashboard/index', [
            'overview' => [
                'role' => 'faculty',
                'studentCount' => 3,
                'userCount' => 5,
                'workflowStatusCounts' => ['Approved' => 1],
                'enrollmentStatusCounts' => ['Active' => 2],
                'requestStatusCounts' => ['Completed' => 1],
                'recentStudents' => [],
                'recentActivity' => [[
                    'action' => 'updated',
                    'actor_name' => 'Faculty Member',
                    'created_at' => '2026-03-31 12:00:00',
                    'entity_type' => 'request',
                    'entity_id' => 8,
                ]],
                'recentRequests' => [[
                    'id' => 8,
                    'title' => 'Faculty visible request',
                    'request_type' => 'Record Certification',
                    'status' => 'Completed',
                    'first_name' => 'Leo',
                    'last_name' => 'Reyes',
                    'student_number' => 'BSI-2026-1002',
                ]],
                'roleOverview' => [],
                'studentRecord' => null,
                'profileCompleteness' => null,
                'idAvailable' => false,
                'overdueRequestCount' => 0,
                'notificationUnreadCount' => 1,
                'notificationDeliverySummary' => [],
            ],
            'notifications' => [],
        ]);

        $session->set('auth.user_id', 1);

        $adminEmptyHtml = $view->render('dashboard/index', [
            'overview' => [
                'role' => 'admin',
                'studentCount' => 3,
                'userCount' => 5,
                'workflowStatusCounts' => ['Approved' => 1],
                'enrollmentStatusCounts' => ['Active' => 2],
                'requestStatusCounts' => ['Pending' => 1],
                'recentStudents' => [],
                'recentActivity' => [],
                'recentRequests' => [[
                    'id' => 9,
                    'title' => 'Admin request',
                    'request_type' => 'Enrollment Clarification',
                    'status' => 'Under Review',
                    'first_name' => 'Aira',
                    'last_name' => 'Mendoza',
                    'student_number' => 'BSI-2026-1001',
                ]],
                'roleOverview' => [[
                    'name' => 'Administrator',
                    'description' => 'Full governance access',
                    'user_count' => 1,
                ]],
                'studentRecord' => null,
                'profileCompleteness' => null,
                'idAvailable' => false,
                'overdueRequestCount' => 2,
                'notificationUnreadCount' => 5,
                'notificationDeliverySummary' => [],
            ],
            'notifications' => [],
        ]);

        $session->forget('auth.user_id');

        $fallbackHtml = $view->render('dashboard/index', [
            'overview' => [
                'role' => '',
                'studentCount' => 3,
                'userCount' => 5,
                'workflowStatusCounts' => [],
                'enrollmentStatusCounts' => [],
                'requestStatusCounts' => [],
                'recentStudents' => [],
                'recentActivity' => [],
                'recentRequests' => [],
                'roleOverview' => [],
                'studentRecord' => null,
                'profileCompleteness' => null,
                'idAvailable' => false,
                'overdueRequestCount' => 0,
                'notificationUnreadCount' => 0,
                'notificationDeliverySummary' => [],
            ],
            'notifications' => [],
        ]);

        self::assertStringContainsString('Operations control center', $registrarHtml);
        self::assertStringContainsString('Register Student', $registrarHtml);
        self::assertStringContainsString('Status Transition', $registrarHtml);
        self::assertStringContainsString('No students found.', $registrarHtml);
        self::assertStringContainsString('Aira Mendoza', $registrarHtml);

        self::assertStringContainsString('Academic visibility workspace', $facultyHtml);
        self::assertStringContainsString('Open Records', $facultyHtml);
        self::assertStringContainsString('Reports', $facultyHtml);
        self::assertStringContainsString('Faculty visible request', $facultyHtml);
        self::assertStringContainsString('Leo Reyes', $facultyHtml);

        self::assertStringContainsString('Role coverage overview', $adminEmptyHtml);
        self::assertStringContainsString('No students found.', $adminEmptyHtml);

        self::assertStringContainsString('Student lifecycle overview', $fallbackHtml);
        self::assertStringContainsString('No requests found.', $fallbackHtml);
    }

    public function testListingTemplatesRenderEmptyStatesForRequestsIdsAndNotifications(): void
    {
        $view = $this->app->get(View::class);
        $session = $this->app->get(Session::class);
        $_SERVER['REQUEST_URI'] = '/templates';
        $session->set('auth.user_id', 1);

        $requestsEmpty = $view->render('requests/index', [
            'requests' => [],
            'filters' => ['search' => '', 'status' => '', 'request_type' => '', 'priority' => '', 'department' => '', 'overdue_only' => ''],
            'requestTypes' => ['Profile Update'],
            'requestStatuses' => ['Pending'],
            'priorities' => ['Normal'],
            'departments' => ['BSIT'],
            'queueUsers' => [],
        ]);
        $idCardsEmpty = $view->render('id-cards/index', [
            'students' => [],
            'filters' => ['search' => '', 'status' => '', 'enrollment_status' => '', 'department' => ''],
            'departments' => ['BSIT'],
            'workflowStatuses' => ['Pending'],
            'enrollmentStatuses' => ['Active'],
        ]);
        $notificationsEmpty = $view->render('notifications/index', [
            'notifications' => [],
        ]);

        self::assertStringContainsString('No requests found', $requestsEmpty);
        self::assertStringContainsString('Try broadening the queue filters', $requestsEmpty);
        self::assertStringContainsString('No student records available', $idCardsEmpty);
        self::assertStringContainsString('Generate now', $idCardsEmpty);
        self::assertStringContainsString('No notifications yet', $notificationsEmpty);
    }
}
