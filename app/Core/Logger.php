<?php

declare(strict_types=1);

namespace App\Core;

final class Logger
{
    private readonly RequestContext $requestContext;

    public function __construct(
        private readonly string $path,
        ?RequestContext $requestContext = null
    ) {
        $this->requestContext = $requestContext ?? new RequestContext();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = [], string $channel = 'app'): void
    {
        $this->write('ERROR', $message, $context, $channel);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = [], string $channel = 'app'): void
    {
        $this->write('INFO', $message, $context, $channel);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = [], string $channel = 'app'): void
    {
        $this->write('WARNING', $message, $context, $channel);
    }

    /**
     * @return list<LogEntry>
     */
    public function recent(int $limit = 20): array
    {
        return $this->entries($limit);
    }

    /**
     * @return list<LogEntry>
     */
    public function entries(?int $limit = null, ?string $channel = null): array
    {
        if ($limit !== null && $limit <= 0) {
            return [];
        }

        if (!is_file($this->path)) {
            return [];
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || $lines === []) {
            return [];
        }

        /** @var list<LogEntry> $entries */
        $entries = [];

        foreach (array_reverse($lines) as $line) {
            $entry = $this->parseLine((string) $line);

            if ($channel !== null && $entry['channel'] !== $channel) {
                continue;
            }

            $entries[] = $entry;

            if ($limit !== null && count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $message, array $context, string $channel): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $payload = json_encode([
            'timestamp' => date('c'),
            'level' => $level,
            'channel' => $channel,
            'request_id' => $this->requestContext->requestId(),
            'message' => $message,
            'context' => $context,
        ], JSON_THROW_ON_ERROR);

        file_put_contents($this->path, $payload . "\n", FILE_APPEND);
    }

    /**
     * @return LogEntry
     */
    private function parseLine(string $line): array
    {
        $decoded = json_decode($line, true);

        if (!is_array($decoded)) {
            return [
                'timestamp' => '',
                'level' => 'INFO',
                'channel' => 'legacy',
                'request_id' => 'legacy',
                'message' => $line,
                'context' => [],
            ];
        }

        return [
            'timestamp' => string_value($decoded['timestamp'] ?? ''),
            'level' => string_value($decoded['level'] ?? 'INFO', 'INFO'),
            'channel' => string_value($decoded['channel'] ?? 'app', 'app'),
            'request_id' => string_value($decoded['request_id'] ?? 'system', 'system'),
            'message' => string_value($decoded['message'] ?? ''),
            'context' => is_array($decoded['context'] ?? null) ? map_value($decoded['context']) : [],
        ];
    }
}
