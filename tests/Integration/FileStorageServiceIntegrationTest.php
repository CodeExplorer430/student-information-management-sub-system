<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\FileStorageService;
use ReflectionMethod;
use RuntimeException;
use Tests\Support\IntegrationTestCase;

final class FileStorageServiceIntegrationTest extends IntegrationTestCase
{
    public function testStoreImageRejectsOversizedUploads(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sims-large-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'x');

        $service = $this->app->get(FileStorageService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Photo exceeds the 5MB limit.');

        try {
            $service->storeImage([
                'tmp_name' => $tmpFile,
                'error' => UPLOAD_ERR_OK,
                'size' => 5_242_881,
            ]);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testStoreImageLogsAndRejectsMoveFailures(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sims-valid-image-');
        self::assertNotFalse($tmpFile);

        $image = imagecreatetruecolor(16, 16);
        self::assertInstanceOf(\GdImage::class, $image);
        imagepng($image, $tmpFile);
        imagedestroy($image);

        $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
        @unlink($logFile);

        $service = $this->app->get(FileStorageService::class);

        try {
            $service->storeImage([
                'tmp_name' => $tmpFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpFile),
            ]);
            self::fail('Expected image move failure was not raised.');
        } catch (RuntimeException $exception) {
            self::assertSame('Unable to save the uploaded photo.', $exception->getMessage());
        } finally {
            @unlink($tmpFile);
        }

        self::assertFileExists($logFile);
        self::assertStringContainsString('Failed to move uploaded file.', (string) file_get_contents($logFile));
    }

    public function testStoreAttachmentCanPersistFileMetadataWhenTestingMoveHookIsEnabled(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sims-attachment-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "attachment body\n");

        $service = $this->app->get(FileStorageService::class);
        $GLOBALS['__sims_test_move_uploaded_files'] = true;

        try {
            $attachment = $service->storeAttachment([
                'name' => 'proof.txt',
                'tmp_name' => $tmpFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpFile),
            ]);
        } finally {
            unset($GLOBALS['__sims_test_move_uploaded_files']);
        }

        self::assertNotNull($attachment);
        self::assertSame('proof.txt', $attachment['original_name']);
        self::assertSame('text/plain', $attachment['mime_type']);
        self::assertFileExists($service->pathFor($attachment['stored_name']));
    }

    public function testStoreImageSupportsJpegAndWebpUploadsWhenTestingMoveHookIsEnabled(): void
    {
        $service = $this->app->get(FileStorageService::class);
        $GLOBALS['__sims_test_move_uploaded_files'] = true;

        try {
            $jpegFile = tempnam(sys_get_temp_dir(), 'sims-jpeg-');
            self::assertNotFalse($jpegFile);
            $jpegImage = imagecreatetruecolor(12, 12);
            self::assertInstanceOf(\GdImage::class, $jpegImage);
            imagejpeg($jpegImage, $jpegFile);
            imagedestroy($jpegImage);

            $jpgStored = $service->storeImage([
                'name' => 'avatar.jpg',
                'tmp_name' => $jpegFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($jpegFile),
            ]);

            self::assertIsString($jpgStored);
            self::assertStringEndsWith('.jpg', $jpgStored);
            self::assertFileExists($service->pathFor($jpgStored));

            if (function_exists('imagewebp')) {
                $webpFile = tempnam(sys_get_temp_dir(), 'sims-webp-');
                self::assertNotFalse($webpFile);
                $webpImage = imagecreatetruecolor(12, 12);
                self::assertInstanceOf(\GdImage::class, $webpImage);
                imagewebp($webpImage, $webpFile);
                imagedestroy($webpImage);

                $webpStored = $service->storeImage([
                    'name' => 'avatar.webp',
                    'tmp_name' => $webpFile,
                    'error' => UPLOAD_ERR_OK,
                    'size' => filesize($webpFile),
                ]);

                self::assertIsString($webpStored);
                self::assertStringEndsWith('.webp', $webpStored);
                self::assertFileExists($service->pathFor($webpStored));
            }
        } finally {
            unset($GLOBALS['__sims_test_move_uploaded_files']);
        }
    }

    public function testImageExtensionHelperCoversWebpBranch(): void
    {
        $service = $this->app->get(FileStorageService::class);
        $imageExtension = new ReflectionMethod(FileStorageService::class, 'imageExtension');
        $imageExtension->setAccessible(true);

        self::assertSame('webp', $imageExtension->invoke($service, 'image/webp'));
    }

    public function testStorageServiceCoversUploadErrorsInvalidMimeAndAttachmentValidation(): void
    {
        $service = $this->app->get(FileStorageService::class);

        self::assertNull($service->storeImage([]));
        self::assertNull($service->storeAttachment([]));

        try {
            $service->storeImage([
                'tmp_name' => '/tmp/missing',
                'error' => UPLOAD_ERR_CANT_WRITE,
                'size' => 1,
            ]);
            self::fail('Expected image upload failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Photo upload failed.', $exception->getMessage());
        }

        try {
            $service->storeAttachment([
                'tmp_name' => '/tmp/missing',
                'error' => UPLOAD_ERR_PARTIAL,
                'size' => 1,
            ]);
            self::fail('Expected attachment upload failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Attachment upload failed.', $exception->getMessage());
        }

        try {
            $service->storeAttachment([
                'tmp_name' => '',
                'error' => UPLOAD_ERR_OK,
                'size' => 1,
            ]);
            self::fail('Expected incomplete payload failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Uploaded file payload is incomplete.', $exception->getMessage());
        }

        $badImage = tempnam(sys_get_temp_dir(), 'sims-bad-image-');
        self::assertNotFalse($badImage);
        file_put_contents($badImage, 'not-an-image');

        try {
            $service->storeImage([
                'tmp_name' => $badImage,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($badImage),
            ]);
            self::fail('Expected invalid image MIME failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Only JPG, PNG, and WEBP images are allowed.', $exception->getMessage());
        } finally {
            @unlink($badImage);
        }

        try {
            $service->storeAttachment([
                'tmp_name' => '/tmp/fake',
                'error' => UPLOAD_ERR_OK,
                'size' => 10_485_761,
            ]);
            self::fail('Expected attachment oversize failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Attachment exceeds the 10MB limit.', $exception->getMessage());
        }

        $badAttachment = tempnam(sys_get_temp_dir(), 'sims-bad-attachment-');
        self::assertNotFalse($badAttachment);
        file_put_contents($badAttachment, '{}');

        try {
            $service->storeAttachment([
                'name' => 'bad.json',
                'tmp_name' => $badAttachment,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($badAttachment),
            ]);
            self::fail('Expected invalid attachment MIME failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('Only PDF, JPG, PNG, WEBP, and TXT attachments are allowed.', $exception->getMessage());
        } finally {
            @unlink($badAttachment);
        }
    }

    public function testStoreAttachmentLogsAndRejectsMoveFailures(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sims-bad-attachment-move-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, "attachment body\n");

        $logFile = dirname(__DIR__, 2) . '/storage/logs/app.log';
        @unlink($logFile);

        $service = $this->app->get(FileStorageService::class);

        try {
            $service->storeAttachment([
                'name' => 'proof.txt',
                'tmp_name' => $tmpFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($tmpFile),
            ]);
            self::fail('Expected attachment move failure was not raised.');
        } catch (RuntimeException $exception) {
            self::assertSame('Unable to save the uploaded attachment.', $exception->getMessage());
        } finally {
            @unlink($tmpFile);
        }

        self::assertFileExists($logFile);
        self::assertStringContainsString('Failed to move uploaded attachment.', (string) file_get_contents($logFile));
    }
}
