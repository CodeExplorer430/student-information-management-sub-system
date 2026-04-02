<?php

declare(strict_types=1);

namespace App\Core;

final class Flash
{
    private const FLASH_KEY = 'flash.messages';

    public function __construct(
        private readonly Session $session
    ) {
    }

    public function add(string $type, string $message): void
    {
        /** @var list<FlashMessage> $messages */
        $messages = $this->session->get(self::FLASH_KEY, []);
        $messages[] = ['type' => $type, 'message' => $message];
        $this->session->set(self::FLASH_KEY, $messages);
    }

    /**
     * @return list<FlashMessage>
     */
    public function pull(): array
    {
        $messages = $this->session->get(self::FLASH_KEY, []);
        $this->session->forget(self::FLASH_KEY);

        if (!is_array($messages)) {
            return [];
        }

        return array_values(array_filter(
            $messages,
            static fn (mixed $message): bool => is_array($message)
                && is_string($message['type'] ?? null)
                && is_string($message['message'] ?? null)
        ));
    }
}
