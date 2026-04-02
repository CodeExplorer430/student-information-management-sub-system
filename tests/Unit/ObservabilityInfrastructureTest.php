<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Logger;
use App\Core\RequestContext;
use PHPUnit\Framework\TestCase;

final class ObservabilityInfrastructureTest extends TestCase
{
    public function testRequestContextGeneratesSeparateHttpAndConsoleCorrelationIds(): void
    {
        $context = new RequestContext();

        $httpId = $context->startHttp();
        $consoleId = $context->startConsole('cli-fixed-id');

        self::assertSame(16, strlen($httpId));
        self::assertSame('cli-fixed-id', $consoleId);
        self::assertSame('console', $context->channel());
        self::assertSame('cli-fixed-id', $context->requestId());
    }

    public function testLoggerWritesStructuredJsonAndReadsRecentEntries(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'sims-observe-log-');
        self::assertNotFalse($logFile);

        try {
            $context = new RequestContext();
            $context->startHttp('req-123');
            $logger = new Logger($logFile, $context);

            $logger->info('Health check ok.', ['status' => 'pass'], 'health');
            $logger->warning('Degraded dependency detected.', ['dependency' => 'mail'], 'health');
            $logger->error('Database failed.', ['driver' => 'sqlite'], 'database');

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($lines);
            self::assertCount(3, $lines);

            $entry = json_decode((string) $lines[0], true);
            self::assertIsArray($entry);
            self::assertSame('INFO', $entry['level'] ?? null);
            self::assertSame('health', $entry['channel'] ?? null);
            self::assertSame('req-123', $entry['request_id'] ?? null);

            $recent = $logger->recent(2);
            self::assertCount(2, $recent);
            self::assertSame('ERROR', $recent[0]['level']);
            self::assertSame('WARNING', $recent[1]['level']);

            $healthEntries = $logger->entries(null, 'health');
            self::assertCount(2, $healthEntries);
            self::assertSame('WARNING', $healthEntries[0]['level']);
            self::assertSame('INFO', $healthEntries[1]['level']);
            self::assertSame([], $logger->entries(0));
        } finally {
            @unlink($logFile);
        }
    }

    public function testLoggerCoversMissingLegacyAndDirectoryCreationBranches(): void
    {
        $context = new RequestContext();
        $logger = new Logger(sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(4)) . '.log', $context);

        self::assertSame([], $logger->recent());

        $legacyFile = tempnam(sys_get_temp_dir(), 'sims-observe-legacy-');
        self::assertNotFalse($legacyFile);

        try {
            file_put_contents($legacyFile, "legacy line\n");
            $legacyLogger = new Logger($legacyFile, $context);
            $legacy = $legacyLogger->recent(5);

            self::assertCount(1, $legacy);
            self::assertSame('legacy', $legacy[0]['channel']);

            file_put_contents($legacyFile, '');
            self::assertSame([], $legacyLogger->recent(5));

            $directory = sys_get_temp_dir() . '/sims-observe-dir-' . bin2hex(random_bytes(4));
            $nestedLog = $directory . '/app.log';
            $nestedLogger = new Logger($nestedLog, $context);
            $nestedLogger->info('Created nested directory.', [], 'health');

            self::assertFileExists($nestedLog);
        } finally {
            @unlink($legacyFile);
            if (isset($nestedLog) && is_file($nestedLog)) {
                @unlink($nestedLog);
            }
            if (isset($directory) && is_dir($directory)) {
                @rmdir($directory);
            }
        }
    }
}
