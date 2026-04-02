<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

/**
 * @phpstan-type RemoteBackupObject array{
 *     object_key: string,
 *     backup_id: string,
 *     archive_checksum: string,
 *     encrypted_checksum: string,
 *     created_at: string,
 *     manifest_version: string,
 *     size_bytes: int
 * }
 * @phpstan-type RemoteStoreConfig array{
 *     driver: string,
 *     bucket: string,
 *     region: string,
 *     endpoint: string,
 *     access_key: string,
 *     secret_key: string,
 *     prefix: string,
 *     path_style: bool
 * }
 * @phpstan-type RemoteStoreResponse array{
 *     status: int,
 *     headers: array<string, string>,
 *     body: string
 * }
 */
final class S3BackupRemoteStore
{
    /**
     * @param callable(string, string, array<string, string>, string): RemoteStoreResponse $requestHandler
     */
    public function __construct(
        private readonly Config $config,
        private readonly mixed $requestHandler
    ) {
    }

    /**
     * @param array{
     *     backup_id: string,
     *     archive_checksum: string,
     *     encrypted_checksum: string,
     *     created_at: string,
     *     manifest_version: string
     * } $metadata
     * @return RemoteBackupObject
     */
    public function push(string $localPath, array $metadata): array
    {
        if (!is_file($localPath)) {
            throw new RuntimeException(sprintf('Backup export [%s] was not found.', $localPath));
        }

        $config = $this->remoteConfig();
        $objectKey = $this->objectKeyForPath($localPath, $config['prefix']);
        $payload = file_get_contents($localPath);

        if ($payload === false) {
            throw new RuntimeException(sprintf('Unable to read backup export [%s].', $localPath));
        }

        $sizeBytes = filesize($localPath);
        if ($sizeBytes === false) {
            throw new RuntimeException(sprintf('Unable to resolve backup export size for [%s].', $localPath));
        }

        $normalized = $this->normalizedMetadata($objectKey, $metadata, $sizeBytes);
        $this->request(
            'PUT',
            $objectKey,
            [],
            $this->metadataHeaders($normalized) + ['content-type' => 'application/octet-stream'],
            $payload,
            $config
        );

        return $normalized;
    }

    /**
     * @return RemoteBackupObject
     */
    public function pull(string $objectKey, string $destinationPath): array
    {
        if ($objectKey === '') {
            throw new RuntimeException('Remote backup object key is required.');
        }

        $response = $this->request('GET', $objectKey, [], [], '', $this->remoteConfig());
        $metadata = $this->metadataFromHeaders($objectKey, $response['headers'], strlen($response['body']));
        $written = file_put_contents($destinationPath, $response['body']);

        if ($written === false) {
            throw new RuntimeException(sprintf('Unable to write downloaded backup export [%s].', $destinationPath));
        }

        return $metadata;
    }

    /**
     * @return list<RemoteBackupObject>
     */
    public function list(): array
    {
        $config = $this->remoteConfig();
        $query = ['list-type' => '2'];

        if ($config['prefix'] !== '') {
            $query['prefix'] = $config['prefix'] . '/';
        }

        $response = $this->request('GET', '', $query, [], '', $config);
        $keys = $this->listKeys($response['body']);
        $objects = [];

        foreach ($keys as $objectKey) {
            $head = $this->request('HEAD', $objectKey, [], [], '', $config);
            $objects[] = $this->metadataFromHeaders($objectKey, $head['headers']);
        }

        usort($objects, static fn (array $left, array $right): int => strcmp($right['created_at'], $left['created_at']));

        /** @var list<RemoteBackupObject> $objects */
        return $objects;
    }

    public function delete(string $objectKey): void
    {
        if ($objectKey === '') {
            throw new RuntimeException('Remote backup object key is required.');
        }

        $this->request('DELETE', $objectKey, [], [], '', $this->remoteConfig());
    }

    /**
     * @param array<string, string> $headers
     * @return RemoteStoreResponse
     */
    public static function curlTransport(string $method, string $url, array $headers, string $body): array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to initialize remote backup request for [%s].', $url));
        }

        $responseHeaders = [];
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_map(
                static fn (string $name, string $value): string => $name . ': ' . $value,
                array_keys($headers),
                array_values($headers)
            ),
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $trimmed = trim($line);

                if ($trimmed === '' || str_starts_with(strtolower($trimmed), 'http/')) {
                    return strlen($line);
                }

                $separator = strpos($trimmed, ':');

                if ($separator === false) {
                    return strlen($line);
                }

                $name = strtolower(trim(substr($trimmed, 0, $separator)));
                $value = trim(substr($trimmed, $separator + 1));
                $responseHeaders[$name] = $value;

                return strlen($line);
            },
        ];

        if ($method === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        } elseif ($body !== '') {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        if (!curl_setopt_array($handle, $options)) {
            curl_close($handle);

            throw new RuntimeException(sprintf('Unable to configure remote backup request for [%s].', $url));
        }

        $payload = curl_exec($handle);

        if ($payload === false) {
            $message = curl_error($handle);
            curl_close($handle);

            throw new RuntimeException(sprintf('Remote backup request failed for [%s]: %s', $url, $message));
        }

        $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return [
            'status' => is_int($statusCode) ? $statusCode : 0,
            'headers' => $responseHeaders,
            'body' => $payload,
        ];
    }

    /**
     * @return RemoteStoreConfig
     */
    private function remoteConfig(): array
    {
        $driver = string_value($this->config->get('backup.remote.driver', ''));
        $bucket = string_value($this->config->get('backup.remote.bucket', ''));
        $region = string_value($this->config->get('backup.remote.region', ''));
        $endpoint = string_value($this->config->get('backup.remote.endpoint', ''));
        $accessKey = string_value($this->config->get('backup.remote.access_key', ''));
        $secretKey = string_value($this->config->get('backup.remote.secret_key', ''));

        if (
            $driver !== 's3'
            || $bucket === ''
            || $region === ''
            || $endpoint === ''
            || $accessKey === ''
            || $secretKey === ''
        ) {
            throw new RuntimeException(
                'Remote backup storage is not configured. Set BACKUP_REMOTE_DRIVER=s3 and the S3 connection settings.'
            );
        }

        return [
            'driver' => $driver,
            'bucket' => $bucket,
            'region' => $region,
            'endpoint' => $endpoint,
            'access_key' => $accessKey,
            'secret_key' => $secretKey,
            'prefix' => trim(string_value($this->config->get('backup.remote.prefix', '')), '/'),
            'path_style' => bool_value($this->config->get('backup.remote.path_style', false)),
        ];
    }

    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     * @param RemoteStoreConfig $config
     * @return RemoteStoreResponse
     */
    private function request(
        string $method,
        string $objectKey,
        array $query,
        array $headers,
        string $body,
        array $config
    ): array {
        [$url, $host, $canonicalUri, $canonicalQuery] = $this->requestTarget($config, $objectKey, $query);
        $timestamp = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);
        $signedHeaders = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $timestamp,
        ];

        foreach ($headers as $name => $value) {
            $signedHeaders[strtolower($name)] = trim($value);
        }

        ksort($signedHeaders);

        $canonicalHeaders = '';

        foreach ($signedHeaders as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
        }

        $signedHeaderNames = implode(';', array_keys($signedHeaders));
        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            $canonicalQuery,
            $canonicalHeaders,
            $signedHeaderNames,
            $payloadHash,
        ]);
        $credentialScope = sprintf('%s/%s/s3/aws4_request', $date, $config['region']);
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);
        $signature = hash_hmac(
            'sha256',
            $stringToSign,
            $this->signingKey($config['secret_key'], $date, $config['region'])
        );
        $signedHeaders['authorization'] = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $config['access_key'],
            $credentialScope,
            $signedHeaderNames,
            $signature
        );

        /** @var callable(string, string, array<string, string>, string): RemoteStoreResponse $handler */
        $handler = $this->requestHandler;
        $response = $handler($method, $url, $signedHeaders, $body);
        $normalized = $this->normalizedResponse($response);

        if ($normalized['status'] < 200 || $normalized['status'] >= 300) {
            throw new RuntimeException(sprintf(
                'Remote backup request failed for [%s %s] with status [%d].',
                $method,
                $objectKey === '' ? '/' : $objectKey,
                $normalized['status']
            ));
        }

        return $normalized;
    }

    /**
     * @param RemoteStoreResponse $response
     * @return RemoteStoreResponse
     */
    private function normalizedResponse(array $response): array
    {
        $headers = [];

        foreach (map_value($response['headers'] ?? []) as $name => $value) {
            $headers[strtolower($name)] = string_value($value);
        }

        return [
            'status' => int_value($response['status'] ?? 0),
            'headers' => $headers,
            'body' => string_value($response['body'] ?? ''),
        ];
    }

    /**
     * @param RemoteStoreConfig $config
     * @param array<string, string> $query
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function requestTarget(array $config, string $objectKey, array $query): array
    {
        $parts = parse_url($config['endpoint']);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('Remote backup endpoint is invalid.');
        }

        $scheme = string_value($parts['scheme']);
        $baseHost = string_value($parts['host']);
        $port = isset($parts['port']) ? int_value($parts['port']) : null;
        $basePath = trim(string_value($parts['path'] ?? ''), '/');
        $host = $config['path_style'] ? $baseHost : $config['bucket'] . '.' . $baseHost;
        $hostHeader = $port !== null && !$this->defaultPort($scheme, $port) ? $host . ':' . (string) $port : $host;

        $pathSegments = [];

        if ($basePath !== '') {
            $pathSegments[] = $basePath;
        }

        if ($config['path_style']) {
            $pathSegments[] = $config['bucket'];
        }

        if ($objectKey !== '') {
            $pathSegments[] = $this->encodedObjectKey($objectKey);
        }

        $canonicalUri = '/' . implode('/', $pathSegments);
        $canonicalQuery = $this->canonicalQuery($query);
        $url = $scheme . '://' . $hostHeader . $canonicalUri;

        if ($canonicalQuery !== '') {
            $url .= '?' . $canonicalQuery;
        }

        return [$url, $hostHeader, $canonicalUri, $canonicalQuery];
    }

    private function defaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
    }

    /**
     * @param array<string, string> $query
     */
    private function canonicalQuery(array $query): string
    {
        if ($query === []) {
            return '';
        }

        $pairs = [];

        foreach ($query as $name => $value) {
            $pairs[] = rawurlencode($name) . '=' . rawurlencode($value);
        }

        sort($pairs, SORT_STRING);

        return implode('&', $pairs);
    }

    private function encodedObjectKey(string $objectKey): string
    {
        $segments = [];

        foreach (explode('/', trim($objectKey, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }

            $segments[] = rawurlencode($segment);
        }

        return implode('/', $segments);
    }

    /**
     * @param array{
     *     backup_id: string,
     *     archive_checksum: string,
     *     encrypted_checksum: string,
     *     created_at: string,
     *     manifest_version: string
     * } $metadata
     * @return RemoteBackupObject
     */
    private function normalizedMetadata(string $objectKey, array $metadata, int $sizeBytes): array
    {
        $backupId = string_value($metadata['backup_id'] ?? '');
        $archiveChecksum = string_value($metadata['archive_checksum'] ?? '');
        $encryptedChecksum = string_value($metadata['encrypted_checksum'] ?? '');
        $createdAt = string_value($metadata['created_at'] ?? '');

        if ($backupId === '' || $archiveChecksum === '' || $encryptedChecksum === '' || $createdAt === '') {
            throw new RuntimeException('Remote backup metadata is incomplete.');
        }

        return [
            'object_key' => $objectKey,
            'backup_id' => $backupId,
            'archive_checksum' => $archiveChecksum,
            'encrypted_checksum' => $encryptedChecksum,
            'created_at' => $createdAt,
            'manifest_version' => string_value($metadata['manifest_version'] ?? 'unknown', 'unknown'),
            'size_bytes' => $sizeBytes,
        ];
    }

    /**
     * @param RemoteBackupObject $metadata
     * @return array<string, string>
     */
    private function metadataHeaders(array $metadata): array
    {
        return [
            'x-amz-meta-backup-id' => $metadata['backup_id'],
            'x-amz-meta-archive-checksum' => $metadata['archive_checksum'],
            'x-amz-meta-encrypted-checksum' => $metadata['encrypted_checksum'],
            'x-amz-meta-created-at' => $metadata['created_at'],
            'x-amz-meta-manifest-version' => $metadata['manifest_version'],
        ];
    }

    /**
     * @param array<string, string> $headers
     * @return RemoteBackupObject
     */
    private function metadataFromHeaders(string $objectKey, array $headers, ?int $sizeBytes = null): array
    {
        $resolvedSize = $sizeBytes ?? int_value($headers['content-length'] ?? -1, -1);
        $metadata = [
            'backup_id' => string_value($headers['x-amz-meta-backup-id'] ?? ''),
            'archive_checksum' => string_value($headers['x-amz-meta-archive-checksum'] ?? ''),
            'encrypted_checksum' => string_value($headers['x-amz-meta-encrypted-checksum'] ?? ''),
            'created_at' => string_value($headers['x-amz-meta-created-at'] ?? ''),
            'manifest_version' => string_value($headers['x-amz-meta-manifest-version'] ?? ''),
        ];

        if ($resolvedSize < 0 || in_array('', $metadata, true)) {
            throw new RuntimeException(sprintf('Remote backup object [%s] is missing required metadata.', $objectKey));
        }

        return $this->normalizedMetadata($objectKey, $metadata, $resolvedSize);
    }

    /**
     * @return list<non-empty-string>
     */
    private function listKeys(string $body): array
    {
        $previousState = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        if (!$xml instanceof \SimpleXMLElement) {
            throw new RuntimeException('Remote backup listing response is invalid.');
        }

        $keys = [];

        foreach ($xml->Contents as $content) {
            $key = trim((string) ($content->Key ?? ''));

            if ($key !== '') {
                $keys[] = $key;
            }
        }

        /** @var list<non-empty-string> $keys */
        return $keys;
    }

    private function objectKeyForPath(string $localPath, string $prefix): string
    {
        $filename = basename($localPath);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            throw new RuntimeException(sprintf('Backup export path [%s] does not resolve to a valid remote object name.', $localPath));
        }

        return $prefix !== '' ? $prefix . '/' . $filename : $filename;
    }

    private function signingKey(string $secretKey, string $date, string $region): string
    {
        $dateKey = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
        $regionKey = hash_hmac('sha256', $region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);

        return hash_hmac('sha256', 'aws4_request', $serviceKey, true);
    }
}
