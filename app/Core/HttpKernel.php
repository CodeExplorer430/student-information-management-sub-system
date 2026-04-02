<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\DatabaseBuilder;
use Throwable;

final class HttpKernel
{
    public function __construct(
        private readonly Application $app
    ) {
    }

    public function handle(string $method, string $uri): HttpResult
    {
        $requestId = $this->app->get(RequestContext::class)->startHttp();

        try {
            if (!$this->shouldSkipReadinessCheck($uri)) {
                $this->ensureDatabaseReady($method, $uri);
            }

            $this->app->get(Router::class)->dispatch($method, $uri);
        } catch (HttpResultException $exception) {
            return $exception->result()->withHeaders([
                'X-Request-Id' => $requestId,
            ]);
        } catch (Throwable $exception) {
            $this->app->get(Logger::class)->error('Unhandled HTTP exception.', [
                'method' => $method,
                'uri' => $uri,
                'message' => $exception->getMessage(),
            ], 'http');

            return HttpResult::html($this->internalErrorBody($requestId), 500, [
                'X-Request-Id' => $requestId,
            ]);
        }

        throw new \RuntimeException('Request completed without producing a response.');
    }

    private function ensureDatabaseReady(string $method, string $uri): void
    {
        try {
            $database = $this->app->get(Database::class)->connection();
            $config = $this->app->get(Config::class);
            $missingTables = DatabaseBuilder::missingRequiredTables($database);

            if ($missingTables !== []) {
                $this->app->get(Logger::class)->warning('HTTP request blocked by outdated schema.', [
                    'method' => $method,
                    'uri' => $uri,
                    'missing_tables' => $missingTables,
                ], 'http');

                throw new HttpResultException($this->setupErrorResponse(
                    'Database migration required',
                    'The application can reach the database, but the schema is behind the current codebase and still needs the latest migration.',
                    [
                        'Missing tables: ' . implode(', ', $missingTables),
                        'Run: composer migrate',
                        'If you want a clean local rebuild of demo data: composer reset-db',
                        'Verify again with: php bin/console env:check',
                        'Database: ' . string_value($config->get('db.database', '')),
                    ]
                ));
            }
        } catch (HttpResultException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->app->get(Logger::class)->warning('HTTP request blocked by unavailable database.', [
                'method' => $method,
                'uri' => $uri,
                'message' => $exception->getMessage(),
            ], 'http');

            throw new HttpResultException($this->setupErrorResponse(
                'Database is unavailable',
                'The application could not establish a usable database connection.',
                [
                    'Check your .env database settings and MySQL service.',
                    'Run: php bin/console env:check',
                    'Technical detail: ' . $exception->getMessage(),
                ]
            ));
        }
    }

    private function shouldSkipReadinessCheck(string $uri): bool
    {
        $path = strtok($uri, '?') ?: '/';

        return str_starts_with($path, '/health/');
    }

    /**
     * @param list<string> $details
     */
    private function setupErrorResponse(string $title, string $message, array $details): HttpResult
    {
        $titleEscaped = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $messageEscaped = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $detailItems = '';

        foreach ($details as $detail) {
            $detailItems .= '<li>' . htmlspecialchars((string) $detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>';
        }

        $body = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
            . $titleEscaped
            . '</title><style>body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f5f9ff;color:#203040}main{max-width:760px;margin:8vh auto;padding:32px}section{background:#fff;border:1px solid #dbe7f3;border-radius:20px;padding:32px;box-shadow:0 24px 48px rgba(31,54,82,.08)}h1{margin:0 0 12px;font-size:2rem}p{margin:0 0 18px;line-height:1.6}ul{margin:0;padding-left:20px;line-height:1.7}.pill{display:inline-block;margin-bottom:14px;padding:6px 12px;border-radius:999px;background:#e8f2ff;color:#1f5f9c;font-size:.8rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}</style></head><body><main><section><div class="pill">Setup Required</div><h1>'
            . $titleEscaped
            . '</h1><p>'
            . $messageEscaped
            . '</p><ul>'
            . $detailItems
            . '</ul></section></main></body></html>';

        return HttpResult::html($body, 503);
    }

    private function internalErrorBody(string $requestId): string
    {
        $requestIdEscaped = htmlspecialchars($requestId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Application Error</title><style>body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f5f9ff;color:#203040}main{max-width:720px;margin:8vh auto;padding:32px}section{background:#fff;border:1px solid #dbe7f3;border-radius:20px;padding:32px;box-shadow:0 24px 48px rgba(31,54,82,.08)}h1{margin:0 0 12px;font-size:2rem}p{margin:0 0 18px;line-height:1.6}code{display:inline-block;padding:4px 8px;border-radius:8px;background:#eef4fb}</style></head><body><main><section><h1>Application error</h1><p>The request could not be completed. Check the application log with the request reference below.</p><p>Request ID: <code>' . $requestIdEscaped . '</code></p></section></main></body></html>';
    }
}
