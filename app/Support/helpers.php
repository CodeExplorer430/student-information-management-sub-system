<?php

declare(strict_types=1);

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value === false) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? false;
    }

    if ($value === false) {
        return $default;
    }

    if (is_string($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value) || is_bool($value)) {
        return (string) $value;
    }

    return $default;
}

function e(mixed $value): string
{
    return htmlspecialchars(string_value($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function string_value(mixed $value, string $default = ''): string
{
    if (is_string($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value) || is_bool($value)) {
        return (string) $value;
    }

    return $default;
}

function nullable_string_value(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }

    return is_array($value) || is_object($value) ? null : string_value($value);
}

function int_value(mixed $value, int $default = 0): int
{
    if (is_int($value)) {
        return $value;
    }

    if (is_float($value)) {
        return (int) $value;
    }

    if (is_string($value) && is_numeric($value)) {
        return (int) $value;
    }

    return $default;
}

function bool_value(mixed $value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_string($value)) {
        $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return $normalized ?? $default;
    }

    return $default;
}

/**
 * @return array<string, mixed>
 */
function map_value(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    /** @var array<string, mixed> $result */
    $result = [];

    foreach ($value as $key => $item) {
        if (is_int($key)) {
            $result[(string) $key] = $item;
            continue;
        }

        $result[(string) $key] = $item;
    }

    /** @var array<string, mixed> $result */
    return $result;
}

/**
 * @return list<array<string, mixed>>
 */
function rows_value(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $rows = [];

    foreach ($value as $row) {
        if (is_array($row)) {
            $rows[] = map_value($row);
        }
    }

    return $rows;
}

/**
 * @return list<string>
 */
function strings_value(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $strings = [];

    foreach ($value as $item) {
        if (is_string($item)) {
            $normalized = trim($item);

            if ($normalized !== '') {
                $strings[] = $normalized;
            }
        }
    }

    return array_values(array_unique($strings));
}

/**
 * @param array<string, mixed> $data
 */
function map_string(array $data, string $key, string $default = ''): string
{
    return string_value($data[$key] ?? $default, $default);
}

/**
 * @param array<string, mixed> $data
 */
function map_int(array $data, string $key, int $default = 0): int
{
    return int_value($data[$key] ?? $default, $default);
}

/**
 * @return UploadedFileRow
 */
function uploaded_file_value(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $file = [];
    $name = nullable_string_value($value['name'] ?? null);
    if ($name !== null) {
        $file['name'] = $name;
    }

    $tmpName = nullable_string_value($value['tmp_name'] ?? null);
    if ($tmpName !== null) {
        $file['tmp_name'] = $tmpName;
    }

    if (isset($value['error'])) {
        $file['error'] = int_value($value['error'], UPLOAD_ERR_NO_FILE);
    }

    if (array_key_exists('size', $value)) {
        $size = $value['size'];
        $file['size'] = $size === false ? false : int_value($size);
    }

    return $file;
}

/**
 * @param ValidationErrors $errors
 */
function first_error(array $errors, string $field): ?string
{
    $messages = $errors[$field] ?? null;
    if (!is_array($messages) || $messages === []) {
        return null;
    }

    $message = $messages[0] ?? null;

    return is_string($message) ? $message : null;
}

/**
 * @param array<string, mixed> $old
 */
function old_input(array $old, string $field, mixed $default = ''): mixed
{
    return $old[$field] ?? $default;
}

function selected(mixed $actual, mixed $expected): string
{
    return string_value($actual) === string_value($expected) ? 'selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function status_slug(?string $status): string
{
    $value = strtolower(trim((string) $status));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function workflow_progress(?string $status): int
{
    return match ($status) {
        'Completed' => 100,
        'Approved', 'Rejected' => 75,
        'Under Review' => 50,
        default => 25,
    };
}

function action_label(?string $action): string
{
    return ucwords(str_replace('_', ' ', (string) $action));
}

function user_initials(?string $name): string
{
    $segments = preg_split('/\s+/', trim((string) $name)) ?: [];
    $initials = '';

    foreach ($segments as $segment) {
        if ($segment === '') {
            continue;
        }

        $initials .= strtoupper($segment[0]);

        if (strlen($initials) === 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'U';
}

function private_upload_data_uri(?string $storedName): string
{
    $storedName = trim((string) $storedName);
    if ($storedName === '') {
        return '';
    }

    $path = dirname(__DIR__, 2) . '/storage/app/private/uploads/' . ltrim($storedName, '/');
    if (!is_file($path)) {
        return '';
    }

    $contents = file_get_contents($path);
    if (!is_string($contents) || $contents === '') {
        return '';
    }

    $mimeType = mime_content_type($path);
    if (!is_string($mimeType) || !str_starts_with($mimeType, 'image/')) {
        return '';
    }

    return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
}
