<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Repositories\StudentRepository;
use App\Services\IdCardService;
use ReflectionMethod;
use Tests\Support\IntegrationTestCase;

final class IdCardServiceIntegrationTest extends IntegrationTestCase
{
    public function testGenerateCreatesReadableCardArtifactAndPersistsScanPayloads(): void
    {
        $service = $this->app->get(IdCardService::class);
        $result = $service->generate(1, 5);
        $service->generate(1, 5);

        $student = $this->app->get(StudentRepository::class)->find(1);
        $card = $service->latestCard(1);
        $idCardCount = (int) $this->app->get(Database::class)
            ->query('SELECT COUNT(*) FROM id_cards WHERE student_id = 1')
            ->fetchColumn();

        self::assertNotNull($student);
        self::assertNotNull($card);
        self::assertSame('student-id-1.png', $result['file_name']);
        self::assertFileExists($result['absolute_path']);
        self::assertNotEmpty($student['photo_path']);
        self::assertFileExists(dirname(__DIR__, 2) . '/storage/app/private/uploads/' . $student['photo_path']);
        self::assertSame($student['student_number'], $card['barcode_payload']);
        $qrPayload = json_decode((string) $card['qr_payload'], true, 512, JSON_THROW_ON_ERROR);
        $appUrl = rtrim(env('APP_URL', 'http://127.0.0.1:18081') ?? 'http://127.0.0.1:18081', '/');
        self::assertIsArray($qrPayload);
        self::assertSame($appUrl . '/id-cards/1/verify', $qrPayload['verify'] ?? null);
        self::assertSame(1, $idCardCount);

        $size = getimagesize($result['absolute_path']);
        self::assertIsArray($size);
        self::assertSame(1080, $size[0]);
        self::assertSame(680, $size[1]);
    }

    public function testGenerateRejectsUnknownStudentAndSupportsPlaceholderPhotos(): void
    {
        $service = $this->app->get(IdCardService::class);

        self::assertNull($service->latestCard(999));

        try {
            $service->generate(999, 5);
            self::fail('Expected missing student generation to fail.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Student not found.', $exception->getMessage());
        }

        $this->app->get(Database::class)
            ->connection()
            ->exec("UPDATE students SET photo_path = '' WHERE id = 2");

        $result = $service->generate(2, 5);

        self::assertFileExists($result['absolute_path']);
        self::assertSame('student-id-2.png', $result['file_name']);
    }

    public function testPrivateHelpersCoverTextSizingCropPathsAndFallbacks(): void
    {
        $service = $this->app->get(IdCardService::class);
        $database = $this->app->get(Database::class)->connection();

        $photoFile = dirname(__DIR__, 2) . '/storage/app/private/uploads/coverage-tall-photo.png';
        $widePhotoFile = dirname(__DIR__, 2) . '/storage/app/private/uploads/coverage-wide-photo.png';
        $image = imagecreatetruecolor(80, 180);
        self::assertInstanceOf(\GdImage::class, $image);
        imagepng($image, $photoFile);
        imagedestroy($image);

        $wideImage = imagecreatetruecolor(180, 80);
        self::assertInstanceOf(\GdImage::class, $wideImage);
        imagepng($wideImage, $widePhotoFile);
        imagedestroy($wideImage);

        $database->exec("UPDATE students SET photo_path = 'coverage-tall-photo.png' WHERE id = 2");
        $generated = $service->generate(2, 5);
        self::assertFileExists($generated['absolute_path']);
        $database->exec("UPDATE students SET photo_path = 'coverage-wide-photo.png' WHERE id = 2");
        $generatedWide = $service->generate(2, 5);
        self::assertFileExists($generatedWide['absolute_path']);

        $truncate = new ReflectionMethod(IdCardService::class, 'truncate');
        $truncate->setAccessible(true);
        $academicYearLabel = new ReflectionMethod(IdCardService::class, 'academicYearLabel');
        $academicYearLabel->setAccessible(true);
        $fontPath = new ReflectionMethod(IdCardService::class, 'fontPath');
        $fontPath->setAccessible(true);
        $createCanvas = new ReflectionMethod(IdCardService::class, 'createCanvas');
        $createCanvas->setAccessible(true);
        $drawText = new ReflectionMethod(IdCardService::class, 'drawText');
        $drawText->setAccessible(true);
        $drawStudentPhoto = new ReflectionMethod(IdCardService::class, 'drawStudentPhoto');
        $drawStudentPhoto->setAccessible(true);
        $allocateColor = new ReflectionMethod(IdCardService::class, 'allocateColor');
        $allocateColor->setAccessible(true);

        self::assertSame('Very long…', $truncate->invoke($service, 'Very long name for coverage', 10));
        $yearLabel = $academicYearLabel->invoke($service);
        self::assertIsString($yearLabel);
        self::assertMatchesRegularExpression('/^\d{4}-\d{4}$/', $yearLabel);
        $resolvedFontPath = $fontPath->invoke($service, false);
        self::assertTrue(is_string($resolvedFontPath) || $resolvedFontPath === null);

        $canvas = $createCanvas->invoke($service);
        self::assertInstanceOf(\GdImage::class, $canvas);

        $textColor = imagecolorallocate($canvas, 0, 0, 0);
        self::assertIsInt($textColor);
        $drawText->invoke($service, $canvas, 'Left', 16, 40, 30, $textColor, false, 'left');
        $drawText->invoke($service, $canvas, 'Center', 16, 120, 60, $textColor, true, 'center');
        $drawText->invoke($service, $canvas, 'Right', 16, 220, 90, $textColor, false, 'right');

        file_put_contents(dirname(__DIR__, 2) . '/storage/app/private/uploads/not-an-image.txt', 'plain text');
        $drawStudentPhoto->invoke($service, $canvas, [
            'first_name' => 'Test',
            'last_name' => 'Student',
            'photo_path' => 'not-an-image.txt',
        ], 10, 10, 80, 100, $textColor, $textColor, $textColor);

        self::assertGreaterThan(0, imagesx($canvas));
        imagedestroy($canvas);

        $paletteCanvas = imagecreate(2, 2);
        self::assertInstanceOf(\GdImage::class, $paletteCanvas);
        for ($index = 0; $index < 256; $index++) {
            imagecolorallocate($paletteCanvas, $index, $index, $index);
        }

        try {
            $allocateColor->invoke($service, $paletteCanvas, 255, 0, 0);
            self::fail('Expected palette allocation exhaustion to fail.');
        } catch (\Throwable $exception) {
            self::assertStringContainsString('Unable to allocate ID card color.', $exception->getMessage());
        }

        imagedestroy($paletteCanvas);

        @unlink(dirname(__DIR__, 2) . '/storage/app/private/uploads/not-an-image.txt');
        @unlink($photoFile);
        @unlink($widePhotoFile);
    }
}
