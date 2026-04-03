<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Repositories\NotificationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Throwable;

final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly UserRepository $users,
        private readonly RoleRepository $roles,
        private readonly Config $config,
        private readonly Logger $logger
    ) {
    }

    public function notifyPermissionRecipients(string $permission, string $entityType, int $entityId, string $title, string $message): void
    {
        $userIds = [];
        foreach ($this->users->all() as $user) {
            if (in_array($permission, $this->roles->permissionsForRoles((array) ($user['roles'] ?? [])), true)) {
                $userIds[] = (int) ($user['id'] ?? 0);
            }
        }

        $this->notifyUserIds($userIds, $entityType, $entityId, $title, $message);
    }

    /**
     * @param list<int> $userIds
     */
    public function notifyUserIds(array $userIds, string $entityType, int $entityId, string $title, string $message): void
    {
        $users = $this->users->findManyByIds($userIds);

        foreach ($users as $user) {
            $notificationId = $this->notifications->create([
                'user_id' => (int) ($user['id'] ?? 0),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'title' => $title,
                'message' => $message,
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->deliverEmail($notificationId, $user, $title, $message);
            $this->deliverSms($notificationId, $user, $message);
        }
    }

    /**
     * @param UserRow $user
     */
    private function deliverEmail(int $notificationId, array $user, string $title, string $message): void
    {
        $recipient = (string) ($user['email'] ?? '');
        if ($recipient === '') {
            return;
        }

        $driver = string_value($this->config->get('notifications.email_driver', 'log'), 'log');
        $fromAddress = string_value($this->config->get('notifications.email_from_address', 'noreply@bcp.edu'), 'noreply@bcp.edu');
        $fromName = string_value($this->config->get('notifications.email_from_name', 'Bestlink SIS'), 'Bestlink SIS');
        $status = 'failed';
        $errorMessage = null;
        $deliveredAt = null;

        try {
            if ($driver === 'mail') {
                $headers = 'From: ' . $fromName . ' <' . $fromAddress . ">\r\n" .
                    'Content-Type: text/plain; charset=UTF-8';
                $sent = mail($recipient, $title, $message, $headers);
                $status = $sent ? 'sent' : 'failed';
                $errorMessage = $sent ? null : 'mail() returned false.';
            } else {
                $this->logger->info('Notification email logged.', [
                    'recipient' => $recipient,
                    'title' => $title,
                ], 'notifications');
                $status = 'sent';
            }
        } catch (Throwable $exception) {
            $errorMessage = $exception->getMessage();
            $this->logger->error('Notification email delivery threw an exception.', [
                'recipient' => $recipient,
                'title' => $title,
                'message' => $exception->getMessage(),
            ], 'notifications');
        }

        if ($status === 'failed' && $errorMessage !== null) {
            $this->logger->warning('Notification email delivery failed.', [
                'recipient' => $recipient,
                'title' => $title,
                'message' => $errorMessage,
            ], 'notifications');
        }

        if ($status === 'sent') {
            $deliveredAt = date('Y-m-d H:i:s');
        }

        $this->notifications->addDelivery([
            'notification_id' => $notificationId,
            'channel' => 'email',
            'recipient' => $recipient,
            'status' => $status,
            'error_message' => $errorMessage,
            'delivered_at' => $deliveredAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param UserRow $user
     */
    private function deliverSms(int $notificationId, array $user, string $message): void
    {
        $recipient = trim((string) ($user['mobile_phone'] ?? ''));
        if ($recipient === '') {
            return;
        }

        $driver = string_value($this->config->get('notifications.sms_driver', 'log'), 'log');
        $status = 'failed';
        $errorMessage = null;
        $deliveredAt = null;

        try {
            if ($driver === 'http') {
                $apiUrl = string_value($this->config->get('notifications.sms_api_url', ''));
                if ($apiUrl === '') {
                    throw new \RuntimeException('SMS API URL is not configured.');
                }

                $payload = json_encode([
                    'recipient' => $recipient,
                    'message' => $message,
                    'sender_id' => string_value($this->config->get('notifications.sms_sender_id', 'BCP'), 'BCP'),
                ], JSON_THROW_ON_ERROR);

                $headers = [
                    'Content-Type: application/json',
                ];
                $token = string_value($this->config->get('notifications.sms_api_token', ''));
                if ($token !== '') {
                    $headers[] = 'Authorization: Bearer ' . $token;
                }

                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => implode("\r\n", $headers),
                        'content' => $payload,
                        'ignore_errors' => true,
                        'timeout' => 10,
                    ],
                ]);

                $result = @file_get_contents($apiUrl, false, $context);
                $status = $result === false ? 'failed' : 'sent';
                $errorMessage = $result === false ? 'SMS HTTP delivery failed.' : null;
            } else {
                $this->logger->info('Notification SMS logged.', [
                    'recipient' => $recipient,
                    'message' => $message,
                ], 'notifications');
                $status = 'sent';
            }
        } catch (Throwable $exception) {
            $errorMessage = $exception->getMessage();
            $this->logger->error('Notification SMS delivery threw an exception.', [
                'recipient' => $recipient,
                'message' => $exception->getMessage(),
            ], 'notifications');
        }

        if ($status === 'failed' && $errorMessage !== null) {
            $this->logger->warning('Notification SMS delivery failed.', [
                'recipient' => $recipient,
                'message' => $errorMessage,
            ], 'notifications');
        }

        if ($status === 'sent') {
            $deliveredAt = date('Y-m-d H:i:s');
        }

        $this->notifications->addDelivery([
            'notification_id' => $notificationId,
            'channel' => 'sms',
            'recipient' => $recipient,
            'status' => $status,
            'error_message' => $errorMessage,
            'delivered_at' => $deliveredAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
