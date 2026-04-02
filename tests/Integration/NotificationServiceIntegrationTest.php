<?php

declare(strict_types=1);

namespace App\Services {
    function mail(string $to, string $subject, string $message, string $headers): bool
    {
        if (($GLOBALS['__sims_test_mail_throw'] ?? false) === true) {
            throw new \RuntimeException('mail transport exploded');
        }

        return false;
    }
}

namespace Tests\Integration {

    use App\Core\Config;
    use App\Core\Logger;
    use App\Repositories\NotificationRepository;
    use App\Repositories\RoleRepository;
    use App\Repositories\UserRepository;
    use App\Services\NotificationService;
    use ReflectionMethod;
    use Tests\Support\IntegrationTestCase;

    final class NotificationServiceIntegrationTest extends IntegrationTestCase
    {
        public function testNotificationServiceCoversLoggedMailAndHttpDeliveryBranches(): void
        {
            $notifications = $this->app->get(NotificationRepository::class);
            $userRepository = $this->app->get(UserRepository::class);
            $roleRepository = $this->app->get(RoleRepository::class);
            $logFile = tempnam(sys_get_temp_dir(), 'sims-notify-');
            self::assertNotFalse($logFile);

            try {
                $service = new NotificationService(
                    $notifications,
                    $userRepository,
                    $roleRepository,
                    new Config([
                        'notifications' => [
                            'email_driver' => 'mail',
                            'email_from_address' => 'noreply@bcp.edu',
                            'email_from_name' => 'SIMS',
                            'sms_driver' => 'http',
                            'sms_api_url' => 'http://127.0.0.1:9',
                            'sms_api_token' => 'test-token',
                            'sms_sender_id' => 'BCP',
                        ],
                    ]),
                    new Logger($logFile)
                );

                $notificationId = $notifications->create([
                    'user_id' => 1,
                    'entity_type' => 'request',
                    'entity_id' => 77,
                    'title' => 'Coverage notification',
                    'message' => 'Delivery branch coverage.',
                    'is_read' => 0,
                    'created_at' => '2026-03-31 12:00:00',
                ]);

                $email = new ReflectionMethod(NotificationService::class, 'deliverEmail');
                $email->setAccessible(true);
                $sms = new ReflectionMethod(NotificationService::class, 'deliverSms');
                $sms->setAccessible(true);

                $email->invoke($service, $notificationId, ['email' => '', 'mobile_phone' => ''], 'Skipped', 'No email');
                $sms->invoke($service, $notificationId, ['email' => 'student@bcp.edu', 'mobile_phone' => ''], 'No sms');
                $email->invoke($service, $notificationId, ['email' => 'student@bcp.edu', 'mobile_phone' => '09170000000'], 'Mail attempt', 'Testing mail branch');
                $sms->invoke($service, $notificationId, ['email' => 'student@bcp.edu', 'mobile_phone' => '09170000000'], 'Testing sms http branch');

                $deliveries = $notifications->searchDeliveries([
                    'search' => 'Coverage notification',
                ]);

                self::assertCount(2, $deliveries);
                self::assertContains($deliveries[0]['channel'], ['email', 'sms']);
                self::assertContains($deliveries[1]['channel'], ['email', 'sms']);
                self::assertContains($deliveries[0]['status'], ['failed', 'sent']);
                self::assertContains($deliveries[1]['status'], ['failed', 'sent']);
            } finally {
                @unlink($logFile);
            }
        }

        public function testNotificationServiceCoversPublicDispatchAndExceptionBranches(): void
        {
            $notifications = $this->app->get(NotificationRepository::class);
            $userRepository = $this->app->get(UserRepository::class);
            $roleRepository = $this->app->get(RoleRepository::class);
            $logFile = tempnam(sys_get_temp_dir(), 'sims-notify-public-');
            self::assertNotFalse($logFile);

            try {
                $service = new NotificationService(
                    $notifications,
                    $userRepository,
                    $roleRepository,
                    new Config([
                        'notifications' => [
                            'email_driver' => 'log',
                            'sms_driver' => 'http',
                            'sms_api_url' => '',
                        ],
                    ]),
                    new Logger($logFile)
                );

                $service->notifyUserIds([3], 'request', 99, 'Direct notify', 'Coverage of notifyUserIds.');
                $service->notifyPermissionRecipients(
                    'requests.view_queue',
                    'request',
                    100,
                    'Permission notify',
                    'Coverage of notifyPermissionRecipients.'
                );

                $deliveries = $notifications->searchDeliveries([
                    'search' => 'notify',
                ]);

                self::assertNotEmpty($deliveries);
                self::assertContains('email', array_column($deliveries, 'channel'));
                self::assertContains('sms', array_column($deliveries, 'channel'));
                self::assertContains('SMS API URL is not configured.', array_column($deliveries, 'error_message'));
            } finally {
                @unlink($logFile);
            }
        }

        public function testNotificationServiceCoversMailExceptionBranch(): void
        {
            $notifications = $this->app->get(NotificationRepository::class);
            $service = new NotificationService(
                $notifications,
                $this->app->get(UserRepository::class),
                $this->app->get(RoleRepository::class),
                new Config([
                    'notifications' => [
                        'email_driver' => 'mail',
                        'email_from_address' => 'noreply@bcp.edu',
                        'email_from_name' => 'SIMS',
                        'sms_driver' => 'log',
                    ],
                ]),
                $this->app->get(Logger::class)
            );

            $notificationId = $notifications->create([
                'user_id' => 1,
                'entity_type' => 'request',
                'entity_id' => 101,
                'title' => 'Mail exception',
                'message' => 'Covers throwable handling.',
                'is_read' => 0,
                'created_at' => '2026-03-31 12:10:00',
            ]);

            $email = new ReflectionMethod(NotificationService::class, 'deliverEmail');
            $email->setAccessible(true);
            $GLOBALS['__sims_test_mail_throw'] = true;

            try {
                $email->invoke($service, $notificationId, ['email' => 'student@bcp.edu'], 'Mail exception', 'Body');
            } finally {
                unset($GLOBALS['__sims_test_mail_throw']);
            }

            $deliveries = $notifications->searchDeliveries(['search' => 'Mail exception']);

            self::assertNotEmpty($deliveries);
            self::assertContains('mail transport exploded', array_column($deliveries, 'error_message'));
        }
    }
}
