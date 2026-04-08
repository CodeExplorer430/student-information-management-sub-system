<?php

declare(strict_types=1);

namespace App\Services {
    if (!function_exists(__NAMESPACE__ . '\\file_get_contents')) {
        function file_get_contents(string $filename, bool $useIncludePath = false, mixed $context = null, int $offset = 0, ?int $length = null): string|false
        {
            $targets = $GLOBALS['__sims_backup_file_get_contents_false'] ?? [];
            $suffixes = $GLOBALS['__sims_backup_file_get_contents_false_suffixes'] ?? [];
            $nthTargets = $GLOBALS['__sims_backup_file_get_contents_false_on_nth'] ?? [];

            if (is_array($targets) && in_array($filename, $targets, true)) {
                return false;
            }

            if (is_array($suffixes)) {
                foreach ($suffixes as $suffix) {
                    if (is_string($suffix) && str_ends_with($filename, $suffix)) {
                        return false;
                    }
                }
            }

            if (is_array($nthTargets) && isset($nthTargets[$filename]) && is_int($nthTargets[$filename])) {
                $counts = $GLOBALS['__sims_backup_file_get_contents_counts'] ?? [];

                if (!is_array($counts)) {
                    $counts = [];
                }

                $existingCount = $counts[$filename] ?? 0;
                $count = is_int($existingCount) ? $existingCount + 1 : 1;
                $counts[$filename] = $count;
                $GLOBALS['__sims_backup_file_get_contents_counts'] = $counts;

                if ($count === $nthTargets[$filename]) {
                    return false;
                }
            }

            $streamContext = is_resource($context) ? $context : null;
            if ($length !== null) {
                $length = max(0, $length);

                return \file_get_contents($filename, $useIncludePath, $streamContext, $offset, $length);
            }

            return \file_get_contents($filename, $useIncludePath, $streamContext, $offset);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\\filesize')) {
        function filesize(string $filename): int|false
        {
            $targets = $GLOBALS['__sims_backup_filesize_false'] ?? [];

            if (is_array($targets) && in_array($filename, $targets, true)) {
                return false;
            }

            return \filesize($filename);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\\file_put_contents')) {
        function file_put_contents(string $filename, mixed $data, int $flags = 0, mixed $context = null): int|false
        {
            $targets = $GLOBALS['__sims_backup_file_put_contents_false'] ?? [];
            $suffixes = $GLOBALS['__sims_backup_file_put_contents_false_suffixes'] ?? [];

            if (is_array($targets) && in_array($filename, $targets, true)) {
                return false;
            }

            if (is_array($suffixes)) {
                foreach ($suffixes as $suffix) {
                    if (is_string($suffix) && str_ends_with($filename, $suffix)) {
                        return false;
                    }
                }
            }

            if (is_bool($targets) && $targets) {
                return false;
            }

            $streamContext = is_resource($context) ? $context : null;

            return \file_put_contents($filename, $data, $flags, $streamContext);
        }
    }

    function curl_init(?string $url = null): \stdClass|false
    {
        if (($GLOBALS['__sims_remote_curl_init_false'] ?? false) === true) {
            return false;
        }

        $handle = (object) ['url' => $url];
        $handles = $GLOBALS['__sims_remote_curl_handles'] ?? [];

        if (!is_array($handles)) {
            $handles = [];
        }

        $handles[spl_object_id($handle)] = [];
        $GLOBALS['__sims_remote_curl_handles'] = $handles;

        return $handle;
    }

    /**
     * @param array<int, mixed> $options
     */
    function curl_setopt_array(object $handle, array $options): bool
    {
        if (($GLOBALS['__sims_remote_curl_setopt_false'] ?? false) === true) {
            return false;
        }

        $handles = $GLOBALS['__sims_remote_curl_handles'] ?? [];

        if (!is_array($handles)) {
            $handles = [];
        }

        $handles[spl_object_id($handle)] = $options;
        $GLOBALS['__sims_remote_curl_handles'] = $handles;

        return true;
    }

    function curl_exec(object $handle): string|false
    {
        if (($GLOBALS['__sims_remote_curl_exec_false'] ?? false) === true) {
            return false;
        }

        $handles = $GLOBALS['__sims_remote_curl_handles'] ?? [];
        $options = is_array($handles) ? ($handles[spl_object_id($handle)] ?? []) : [];

        if (!is_array($options)) {
            $options = [];
        }

        $headerFunction = $options[CURLOPT_HEADERFUNCTION] ?? null;
        $responseHeaders = $GLOBALS['__sims_remote_curl_response_headers'] ?? [];

        if (!is_array($responseHeaders)) {
            $responseHeaders = [];
        }

        if (is_callable($headerFunction)) {
            $headerFunction($handle, "HTTP/1.1 200 OK\r\n");

            foreach ($responseHeaders as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $headerFunction($handle, $name . ': ' . $value . "\r\n");
                }
            }

            $rawHeaders = $GLOBALS['__sims_remote_curl_raw_headers'] ?? [];

            if (is_array($rawHeaders)) {
                foreach ($rawHeaders as $line) {
                    if (is_string($line)) {
                        $headerFunction($handle, $line . "\r\n");
                    }
                }
            }

            $headerFunction($handle, "\r\n");
        }

        return string_value($GLOBALS['__sims_remote_curl_exec_body'] ?? '');
    }

    function curl_getinfo(object $handle, int $option = 0): mixed
    {
        if ($option === CURLINFO_RESPONSE_CODE) {
            return int_value($GLOBALS['__sims_remote_curl_response_code'] ?? 200, 200);
        }

        return null;
    }

    function curl_error(object $handle): string
    {
        return string_value($GLOBALS['__sims_remote_curl_error'] ?? 'simulated curl error', 'simulated curl error');
    }

    function curl_close(object $handle): void
    {
        $handles = $GLOBALS['__sims_remote_curl_handles'] ?? [];

        if (!is_array($handles)) {
            return;
        }

        unset($handles[spl_object_id($handle)]);
        $GLOBALS['__sims_remote_curl_handles'] = $handles;
    }
}

namespace Tests\Unit {

    use App\Core\Config;
    use App\Services\S3BackupRemoteStore;
    use PHPUnit\Framework\TestCase;
    use ReflectionMethod;
    use RuntimeException;

    final class S3BackupRemoteStoreTest extends TestCase
    {
        private string $rootPath;

        protected function setUp(): void
        {
            parent::setUp();

            $this->rootPath = sys_get_temp_dir() . '/sims-remote-store-' . bin2hex(random_bytes(4));
            mkdir($this->rootPath, 0775, true);
        }

        protected function tearDown(): void
        {
            unset(
                $GLOBALS['__sims_backup_file_get_contents_false'],
                $GLOBALS['__sims_backup_file_put_contents_false'],
                $GLOBALS['__sims_backup_filesize_false'],
                $GLOBALS['__sims_remote_curl_init_false'],
                $GLOBALS['__sims_remote_curl_setopt_false'],
                $GLOBALS['__sims_remote_curl_exec_false'],
                $GLOBALS['__sims_remote_curl_exec_body'],
                $GLOBALS['__sims_remote_curl_response_code'],
                $GLOBALS['__sims_remote_curl_response_headers'],
                $GLOBALS['__sims_remote_curl_raw_headers'],
                $GLOBALS['__sims_remote_curl_error'],
                $GLOBALS['__sims_remote_curl_handles']
            );

            $this->removeDirectory($this->rootPath);

            parent::tearDown();
        }

        public function testPushListAndPullUseConfiguredS3Requests(): void
        {
            $calls = [];
            $store = $this->store(
                [
                    'driver' => 's3',
                    'bucket' => 'test-bucket',
                    'region' => 'us-east-1',
                    'endpoint' => 'https://s3.example.test',
                    'access_key' => 'access-key',
                    'secret_key' => 'secret-key',
                    'prefix' => 'exports',
                    'path_style' => true,
                ],
                function (string $method, string $url, array $headers, string $body) use (&$calls): array {
                    $calls[] = [$method, $url, $headers, $body];

                    if ($method === 'PUT') {
                        return ['status' => 200, 'headers' => [], 'body' => ''];
                    }

                    if ($method === 'GET' && str_contains($url, 'list-type=2')) {
                        return [
                            'status' => 200,
                            'headers' => [],
                            'body' => '<ListBucketResult><Contents><Key>exports/backup.enc</Key></Contents></ListBucketResult>',
                        ];
                    }

                    $metadataHeaders = [
                        'content-length' => '12',
                        'x-amz-meta-backup-id' => 'backup-id',
                        'x-amz-meta-archive-checksum' => 'archive-checksum',
                        'x-amz-meta-encrypted-checksum' => hash('sha256', 'remote-export'),
                        'x-amz-meta-created-at' => '2026-04-01T00:00:00+00:00',
                        'x-amz-meta-manifest-version' => 'test-build',
                    ];

                    if ($method === 'HEAD') {
                        return [
                            'status' => 200,
                            'headers' => $metadataHeaders,
                            'body' => '',
                        ];
                    }

                    return [
                        'status' => 200,
                        'headers' => $metadataHeaders,
                        'body' => 'remote-export',
                    ];
                }
            );

            $localExport = $this->rootPath . '/backup.enc';
            file_put_contents($localExport, 'remote-export');

            $pushed = $store->push($localExport, [
                'backup_id' => 'backup-id',
                'archive_checksum' => 'archive-checksum',
                'encrypted_checksum' => hash('sha256', 'remote-export'),
                'created_at' => '2026-04-01T00:00:00+00:00',
                'manifest_version' => 'test-build',
            ]);
            self::assertSame('exports/backup.enc', $pushed['object_key']);

            $listed = $store->list();
            self::assertCount(1, $listed);
            self::assertSame('exports/backup.enc', $listed[0]['object_key']);

            $pulledPath = $this->rootPath . '/downloaded.enc';
            $pulled = $store->pull('exports/backup.enc', $pulledPath);
            self::assertSame('backup-id', $pulled['backup_id']);
            self::assertSame('remote-export', (string) file_get_contents($pulledPath));
            $store->delete('exports/backup.enc');

            self::assertCount(5, $calls);
            self::assertSame('PUT', $calls[0][0]);
            self::assertSame('https://s3.example.test/test-bucket/exports/backup.enc', $calls[0][1]);
            self::assertStringContainsString('AWS4-HMAC-SHA256 Credential=access-key/', string_value($calls[0][2]['authorization'] ?? ''));
            self::assertSame('GET', $calls[1][0]);
            self::assertStringContainsString('list-type=2', $calls[1][1]);
            self::assertSame('HEAD', $calls[2][0]);
            self::assertSame('GET', $calls[3][0]);
            self::assertSame('DELETE', $calls[4][0]);
        }

        public function testVirtualHostedRequestsAndRemoteFailuresAreRejected(): void
        {
            $store = $this->store(
                [
                    'driver' => 's3',
                    'bucket' => 'test-bucket',
                    'region' => 'us-east-1',
                    'endpoint' => 'https://objects.example.test/base',
                    'access_key' => 'access-key',
                    'secret_key' => 'secret-key',
                    'prefix' => 'exports',
                    'path_style' => false,
                ],
                static function (string $method, string $url): array {
                    return [
                        'status' => 500,
                        'headers' => [],
                        'body' => $url,
                    ];
                }
            );

            $localExport = $this->rootPath . '/backup.enc';
            file_put_contents($localExport, 'body');

            try {
                $store->push($localExport, [
                    'backup_id' => 'backup-id',
                    'archive_checksum' => 'archive',
                    'encrypted_checksum' => hash('sha256', 'body'),
                    'created_at' => '2026-04-01T00:00:00+00:00',
                    'manifest_version' => 'test-build',
                ]);
                self::fail('Expected push failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Remote backup request failed for [PUT exports/backup.enc]', $exception->getMessage());
            }

            $invalidListStore = $this->store(
                [
                    'driver' => 's3',
                    'bucket' => 'test-bucket',
                    'region' => 'us-east-1',
                    'endpoint' => 'https://objects.example.test',
                    'access_key' => 'access-key',
                    'secret_key' => 'secret-key',
                    'prefix' => '',
                    'path_style' => false,
                ],
                static fn (): array => ['status' => 200, 'headers' => [], 'body' => '{invalid-xml']
            );

            try {
                $invalidListStore->list();
                self::fail('Expected invalid list response failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('listing response is invalid', $exception->getMessage());
            }

            $missingMetadataStore = $this->store(
                [
                    'driver' => 's3',
                    'bucket' => 'test-bucket',
                    'region' => 'us-east-1',
                    'endpoint' => 'https://objects.example.test',
                    'access_key' => 'access-key',
                    'secret_key' => 'secret-key',
                    'prefix' => '',
                    'path_style' => false,
                ],
                static fn (): array => ['status' => 200, 'headers' => [], 'body' => 'payload']
            );

            try {
                $missingMetadataStore->pull('exports/backup.enc', $this->rootPath . '/missing.enc');
                self::fail('Expected missing metadata failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('missing required metadata', $exception->getMessage());
            }

            try {
                $missingMetadataStore->pull('', $this->rootPath . '/missing.enc');
                self::fail('Expected missing object key failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('object key is required', $exception->getMessage());
            }

            try {
                $missingMetadataStore->delete('');
                self::fail('Expected missing delete object key failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('object key is required', $exception->getMessage());
            }

            try {
                $missingMetadataStore->push($this->rootPath . '/missing.enc', [
                    'backup_id' => 'backup-id',
                    'archive_checksum' => 'archive',
                    'encrypted_checksum' => 'encrypted',
                    'created_at' => '2026-04-01T00:00:00+00:00',
                    'manifest_version' => 'test-build',
                ]);
                self::fail('Expected missing export path failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('was not found', $exception->getMessage());
            }

            $unreadableExport = $this->rootPath . '/unreadable.enc';
            file_put_contents($unreadableExport, 'payload');
            $GLOBALS['__sims_backup_file_get_contents_false'] = [$unreadableExport];

            try {
                $missingMetadataStore->push($unreadableExport, [
                    'backup_id' => 'backup-id',
                    'archive_checksum' => 'archive',
                    'encrypted_checksum' => 'encrypted',
                    'created_at' => '2026-04-01T00:00:00+00:00',
                    'manifest_version' => 'test-build',
                ]);
                self::fail('Expected unreadable export failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to read backup export', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_get_contents_false']);
            }

            $GLOBALS['__sims_backup_filesize_false'] = [$unreadableExport];

            try {
                $missingMetadataStore->push($unreadableExport, [
                    'backup_id' => 'backup-id',
                    'archive_checksum' => 'archive',
                    'encrypted_checksum' => 'encrypted',
                    'created_at' => '2026-04-01T00:00:00+00:00',
                    'manifest_version' => 'test-build',
                ]);
                self::fail('Expected missing export size failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to resolve backup export size', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_filesize_false']);
            }

            $writableFailureStore = $this->store(
                [
                    'driver' => 's3',
                    'bucket' => 'test-bucket',
                    'region' => 'us-east-1',
                    'endpoint' => 'https://objects.example.test',
                    'access_key' => 'access-key',
                    'secret_key' => 'secret-key',
                    'prefix' => '',
                    'path_style' => false,
                ],
                static fn (): array => [
                    'status' => 200,
                    'headers' => [
                        'content-length' => '7',
                        'x-amz-meta-backup-id' => 'backup-id',
                        'x-amz-meta-archive-checksum' => 'archive-checksum',
                        'x-amz-meta-encrypted-checksum' => 'encrypted-checksum',
                        'x-amz-meta-created-at' => '2026-04-01T00:00:00+00:00',
                        'x-amz-meta-manifest-version' => 'test-build',
                    ],
                    'body' => 'payload',
                ]
            );
            $blockedDownloadPath = $this->rootPath . '/blocked-download.enc';
            $GLOBALS['__sims_backup_file_put_contents_false'] = [$blockedDownloadPath];

            try {
                $writableFailureStore->pull('exports/backup.enc', $blockedDownloadPath);
                self::fail('Expected download write failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to write downloaded backup export', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_backup_file_put_contents_false']);
            }
        }

        public function testConfigurationAndPrivateHelpersCoverRemainingBranches(): void
        {
            $unconfiguredStore = $this->store([], static fn (): array => ['status' => 200, 'headers' => [], 'body' => '']);

            try {
                $unconfiguredStore->list();
                self::fail('Expected missing configuration failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Remote backup storage is not configured.', $exception->getMessage());
            }

            $store = $this->store(
                [
                    'driver' => 's3',
                    'bucket' => 'test-bucket',
                    'region' => 'us-east-1',
                    'endpoint' => 'https://objects.example.test',
                    'access_key' => 'access-key',
                    'secret_key' => 'secret-key',
                    'prefix' => '',
                    'path_style' => true,
                ],
                static fn (): array => ['status' => 200, 'headers' => [], 'body' => '']
            );

            $canonicalQuery = new ReflectionMethod(S3BackupRemoteStore::class, 'canonicalQuery');
            $canonicalQuery->setAccessible(true);
            self::assertSame('', $canonicalQuery->invoke($store, []));
            self::assertSame('a=1&b=2', $canonicalQuery->invoke($store, ['b' => '2', 'a' => '1']));

            $encodedObjectKey = new ReflectionMethod(S3BackupRemoteStore::class, 'encodedObjectKey');
            $encodedObjectKey->setAccessible(true);
            self::assertSame('folder/file%20name.enc', $encodedObjectKey->invoke($store, 'folder/file name.enc'));
            self::assertSame('folder/file.enc', $encodedObjectKey->invoke($store, 'folder//file.enc'));

            $defaultPort = new ReflectionMethod(S3BackupRemoteStore::class, 'defaultPort');
            $defaultPort->setAccessible(true);
            self::assertTrue($defaultPort->invoke($store, 'https', 443));
            self::assertFalse($defaultPort->invoke($store, 'https', 444));

            $objectKeyForPath = new ReflectionMethod(S3BackupRemoteStore::class, 'objectKeyForPath');
            $objectKeyForPath->setAccessible(true);

            try {
                $objectKeyForPath->invoke($store, '/', '');
                self::fail('Expected invalid object key failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('does not resolve to a valid remote object name', $exception->getMessage());
            }

            $metadataFromHeaders = new ReflectionMethod(S3BackupRemoteStore::class, 'metadataFromHeaders');
            $metadataFromHeaders->setAccessible(true);

            try {
                $metadataFromHeaders->invoke($store, 'exports/backup.enc', ['x-amz-meta-backup-id' => '']);
                self::fail('Expected missing metadata failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('missing required metadata', $exception->getMessage());
            }

            $normalizedMetadata = new ReflectionMethod(S3BackupRemoteStore::class, 'normalizedMetadata');
            $normalizedMetadata->setAccessible(true);

            try {
                $normalizedMetadata->invoke($store, 'exports/backup.enc', [
                    'backup_id' => '',
                    'archive_checksum' => '',
                    'encrypted_checksum' => '',
                    'created_at' => '',
                    'manifest_version' => 'test-build',
                ], 10);
                self::fail('Expected incomplete normalized metadata failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('metadata is incomplete', $exception->getMessage());
            }

            $requestTarget = new ReflectionMethod(S3BackupRemoteStore::class, 'requestTarget');
            $requestTarget->setAccessible(true);

            try {
                $requestTarget->invoke($store, [
                    'driver' => 's3',
                    'bucket' => 'bucket',
                    'region' => 'us-east-1',
                    'endpoint' => '::invalid::',
                    'access_key' => 'access',
                    'secret_key' => 'secret',
                    'prefix' => '',
                    'path_style' => true,
                ], '', []);
                self::fail('Expected invalid endpoint failure.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('endpoint is invalid', $exception->getMessage());
            }
        }

        public function testCurlTransportCoversSuccessAndFailureBranches(): void
        {
            $GLOBALS['__sims_remote_curl_response_headers'] = ['X-Test' => 'value'];
            $GLOBALS['__sims_remote_curl_exec_body'] = 'payload';
            $GLOBALS['__sims_remote_curl_response_code'] = 204;

            $success = S3BackupRemoteStore::curlTransport('PUT', 'https://s3.example.test/object', ['content-type' => 'text/plain'], 'payload');
            self::assertSame(204, $success['status']);
            self::assertSame('value', $success['headers']['x-test'] ?? null);
            self::assertSame('payload', $success['body']);

            $GLOBALS['__sims_remote_curl_raw_headers'] = ['invalid-header-line'];

            $withInvalidHeaderLine = S3BackupRemoteStore::curlTransport(
                'GET',
                'https://s3.example.test/object',
                [],
                ''
            );
            self::assertSame(204, $withInvalidHeaderLine['status']);

            $head = S3BackupRemoteStore::curlTransport('HEAD', 'https://s3.example.test/object', [], '');
            self::assertSame(204, $head['status']);

            $GLOBALS['__sims_remote_curl_init_false'] = true;

            try {
                S3BackupRemoteStore::curlTransport('GET', 'https://s3.example.test/object', [], '');
                self::fail('Expected curl init failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to initialize remote backup request', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_remote_curl_init_false']);
            }

            $GLOBALS['__sims_remote_curl_setopt_false'] = true;

            try {
                S3BackupRemoteStore::curlTransport('GET', 'https://s3.example.test/object', [], '');
                self::fail('Expected curl option failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Unable to configure remote backup request', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_remote_curl_setopt_false']);
            }

            $GLOBALS['__sims_remote_curl_exec_false'] = true;
            $GLOBALS['__sims_remote_curl_error'] = 'boom';

            try {
                S3BackupRemoteStore::curlTransport('GET', 'https://s3.example.test/object', [], '');
                self::fail('Expected curl exec failure.');
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('Remote backup request failed for [https://s3.example.test/object]: boom', $exception->getMessage());
            }
        }

        /**
         * @param array<string, mixed> $remoteConfig
         */
        private function store(array $remoteConfig, callable $requestHandler): S3BackupRemoteStore
        {
            $config = new Config([
                'backup' => [
                    'remote' => $remoteConfig,
                ],
            ]);

            return new S3BackupRemoteStore($config, $requestHandler);
        }

        private function removeDirectory(string $directory): void
        {
            if (!is_dir($directory)) {
                return;
            }

            $entries = scandir($directory);

            if ($entries === false) {
                return;
            }

            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $directory . '/' . $entry;

                if (is_dir($path)) {
                    $this->removeDirectory($path);
                    continue;
                }

                @unlink($path);
            }

            @rmdir($directory);
        }
    }
}
