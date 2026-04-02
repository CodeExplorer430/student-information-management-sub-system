<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use RuntimeException;

final class FileStorageService
{
    private const MAX_FILE_SIZE = 5242880;
    private const ATTACHMENT_MAX_FILE_SIZE = 10485760;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private readonly string $storagePath,
        private readonly Logger $logger
    ) {
    }

    /**
     * @param array{name?: string, tmp_name?: string, error?: int, size?: int|false} $file
     */
    public function storeImage(array $file, string $prefix = 'student-photo'): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Photo upload failed.');
        }

        $tmpName = $this->tmpName($file);
        $size = $this->fileSize($file);

        if ($size > self::MAX_FILE_SIZE) {
            throw new RuntimeException('Photo exceeds the 5MB limit.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpName);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException('Only JPG, PNG, and WEBP images are allowed.');
        }

        $extension = $this->imageExtension($mimeType);

        $safeName = sprintf('%s-%s.%s', $prefix, bin2hex(random_bytes(6)), $extension);
        $destination = $this->storagePath . '/' . $safeName;

        if (!$this->moveUploadedFile($tmpName, $destination)) {
            $this->logger->error('Failed to move uploaded file.', [
                'destination' => $destination,
                'source' => $tmpName,
                'original_name' => string_value($file['name'] ?? ''),
            ], 'storage');
            throw new RuntimeException('Unable to save the uploaded photo.');
        }

        return $safeName;
    }

    /**
     * @param array{name?: string, tmp_name?: string, error?: int, size?: int|false} $file
     * @return array{stored_name:string, original_name:string, mime_type:string, file_size:int}|null
     */
    public function storeAttachment(array $file, string $prefix = 'request-attachment'): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Attachment upload failed.');
        }

        $tmpName = $this->tmpName($file);
        $size = $this->fileSize($file);

        if ($size > self::ATTACHMENT_MAX_FILE_SIZE) {
            throw new RuntimeException('Attachment exceeds the 10MB limit.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpName);
        $allowed = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'text/plain' => 'txt',
        ];

        if (!is_string($mimeType) || !array_key_exists($mimeType, $allowed)) {
            throw new RuntimeException('Only PDF, JPG, PNG, WEBP, and TXT attachments are allowed.');
        }

        $safeName = sprintf('%s-%s.%s', $prefix, bin2hex(random_bytes(6)), $allowed[$mimeType]);
        $destination = $this->storagePath . '/' . $safeName;

        if (!$this->moveUploadedFile($tmpName, $destination)) {
            $this->logger->error('Failed to move uploaded attachment.', [
                'destination' => $destination,
                'source' => $tmpName,
                'original_name' => string_value($file['name'] ?? ''),
            ], 'storage');
            throw new RuntimeException('Unable to save the uploaded attachment.');
        }

        return [
            'stored_name' => $safeName,
            'original_name' => (string) ($file['name'] ?? $safeName),
            'mime_type' => $mimeType,
            'file_size' => $size,
        ];
    }

    public function pathFor(string $storedName): string
    {
        return $this->storagePath . '/' . ltrim($storedName, '/');
    }

    /**
     * @param array{tmp_name?: string, error?: int, size?: int|false} $file
     */
    private function tmpName(array $file): string
    {
        $tmpName = $file['tmp_name'] ?? null;

        if (!is_string($tmpName) || $tmpName === '') {
            throw new RuntimeException('Uploaded file payload is incomplete.');
        }

        return $tmpName;
    }

    /**
     * @param array{size?: int|false} $file
     */
    private function fileSize(array $file): int
    {
        $size = $file['size'] ?? 0;

        return is_int($size) ? $size : 0;
    }

    /**
     * @param 'image/jpeg'|'image/png'|'image/webp' $mimeType
     */
    private function imageExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        };
    }

    private function moveUploadedFile(string $source, string $destination): bool
    {
        if (($GLOBALS['__sims_test_move_uploaded_files'] ?? false) === true) {
            return rename($source, $destination);
        }

        return move_uploaded_file($source, $destination);
    }
}
