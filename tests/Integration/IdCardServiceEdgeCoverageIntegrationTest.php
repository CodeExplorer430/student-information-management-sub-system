<?php

declare(strict_types=1);

namespace App\Services {
    function function_exists(string $function): bool
    {
        if ($function === 'imagecreatetruecolor' && ($GLOBALS['__sims_services_disable_gd'] ?? false) === true) {
            return false;
        }

        if ($function === 'imagettftext' && array_key_exists('__sims_services_imagettftext_exists', $GLOBALS)) {
            return (bool) $GLOBALS['__sims_services_imagettftext_exists'];
        }

        if ($function === 'imagecreatefromwebp' && array_key_exists('__sims_services_imagecreatefromwebp_exists', $GLOBALS)) {
            return (bool) $GLOBALS['__sims_services_imagecreatefromwebp_exists'];
        }

        return \function_exists($function);
    }

    function file_exists(string $filename): bool
    {
        $overrides = $GLOBALS['__sims_services_file_exists'] ?? [];

        if (is_array($overrides) && array_key_exists($filename, $overrides)) {
            return (bool) $overrides[$filename];
        }

        return \file_exists($filename);
    }

    function mime_content_type(string $filename): string|false
    {
        $overrides = $GLOBALS['__sims_services_mime_types'] ?? [];

        if (is_array($overrides) && array_key_exists($filename, $overrides)) {
            $mimeType = $overrides[$filename];

            return is_string($mimeType) ? $mimeType : false;
        }

        return \mime_content_type($filename);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, float|int>|false
     */
    function imagettfbbox(float $size, float $angle, string $font_filename, string $string, array $options = []): array|false
    {
        if (($GLOBALS['__sims_services_fake_bbox'] ?? null) !== null) {
            /** @var array<int, float|int>|false $bbox */
            $bbox = $GLOBALS['__sims_services_fake_bbox'];

            return $bbox;
        }

        $bbox = \imagettfbbox($size, $angle, $font_filename, $string, $options);
        /** @var array<int, float|int>|false $bbox */
        return $bbox;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, float|int>|false
     */
    function imagettftext(
        \GdImage $image,
        float $size,
        float $angle,
        int $x,
        int $y,
        int $color,
        string $font_filename,
        string $text,
        array $options = []
    ): array|false {
        if (($GLOBALS['__sims_services_fake_ttf'] ?? false) === true) {
            if (!isset($GLOBALS['__sims_services_fake_ttf_calls']) || !is_array($GLOBALS['__sims_services_fake_ttf_calls'])) {
                $GLOBALS['__sims_services_fake_ttf_calls'] = [];
            }

            $GLOBALS['__sims_services_fake_ttf_calls'][] = [$x, $y, $text];

            return [0, 0, 10, 0];
        }

        $result = \imagettftext($image, $size, $angle, $x, $y, $color, $font_filename, $text, $options);
        /** @var array<int, float|int>|false $result */
        return $result;
    }
}

namespace Tests\Integration {

    use App\Services\IdCardService;
    use ReflectionMethod;
    use Tests\Support\IntegrationTestCase;

    final class IdCardServiceEdgeCoverageIntegrationTest extends IntegrationTestCase
    {
        public function testCreateCanvasCoversMissingGdGuard(): void
        {
            $service = $this->app->get(IdCardService::class);
            $createCanvas = new ReflectionMethod(IdCardService::class, 'createCanvas');
            $createCanvas->setAccessible(true);
            $GLOBALS['__sims_services_disable_gd'] = true;

            try {
                $createCanvas->invoke($service);
                self::fail('Expected missing GD guard to throw.');
            } catch (\Throwable $exception) {
                self::assertStringContainsString('GD extension is required for ID generation.', $exception->getMessage());
            } finally {
                unset($GLOBALS['__sims_services_disable_gd']);
            }
        }

        public function testFontPathAndDrawTextCoverTrueTypeAlignmentBranches(): void
        {
            $service = $this->app->get(IdCardService::class);
            $fontPath = new ReflectionMethod(IdCardService::class, 'fontPath');
            $fontPath->setAccessible(true);
            $drawText = new ReflectionMethod(IdCardService::class, 'drawText');
            $drawText->setAccessible(true);

            $font = '/tmp/sims-fake-font.ttf';
            $GLOBALS['__sims_services_file_exists'] = [
                $font => true,
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf' => true,
            ];
            $GLOBALS['__sims_services_imagettftext_exists'] = true;
            $GLOBALS['__sims_services_fake_bbox'] = [0, 0, 40, 0, 40, -10, 0, -10];
            $GLOBALS['__sims_services_fake_ttf'] = true;
            $GLOBALS['__sims_services_fake_ttf_calls'] = [];

            $canvas = imagecreatetruecolor(200, 120);
            self::assertInstanceOf(\GdImage::class, $canvas);
            $color = imagecolorallocate($canvas, 0, 0, 0);
            self::assertIsInt($color);

            try {
                self::assertSame('/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', $fontPath->invoke($service, false));

                $drawText->invoke($service, $canvas, 'Center', 16, 100, 40, $color, false, 'center');
                $drawText->invoke($service, $canvas, 'Right', 16, 120, 80, $color, false, 'right');
                /** @var array<int, array{0:int, 1:int, 2:string}> $calls */
                $calls = $GLOBALS['__sims_services_fake_ttf_calls'];
                self::assertCount(2, $calls);
            } finally {
                imagedestroy($canvas);
                unset(
                    $GLOBALS['__sims_services_file_exists'],
                    $GLOBALS['__sims_services_imagettftext_exists'],
                    $GLOBALS['__sims_services_fake_bbox'],
                    $GLOBALS['__sims_services_fake_ttf'],
                    $GLOBALS['__sims_services_fake_ttf_calls']
                );
            }
        }

        public function testDrawStudentPhotoCoversWebpFallbackBranch(): void
        {
            $service = $this->app->get(IdCardService::class);
            $drawStudentPhoto = new ReflectionMethod(IdCardService::class, 'drawStudentPhoto');
            $drawStudentPhoto->setAccessible(true);

            $photoPath = dirname(__DIR__, 2) . '/storage/app/private/uploads/fake.webp';
            $GLOBALS['__sims_services_file_exists'] = [$photoPath => true];
            $GLOBALS['__sims_services_mime_types'] = [$photoPath => 'image/webp'];
            $GLOBALS['__sims_services_imagecreatefromwebp_exists'] = false;

            $canvas = imagecreatetruecolor(200, 160);
            self::assertInstanceOf(\GdImage::class, $canvas);
            $color = imagecolorallocate($canvas, 255, 255, 255);
            self::assertIsInt($color);

            try {
                $drawStudentPhoto->invoke($service, $canvas, [
                    'first_name' => 'Webp',
                    'last_name' => 'Fallback',
                    'photo_path' => 'fake.webp',
                ], 10, 10, 80, 100, $color, $color, $color);
            } finally {
                imagedestroy($canvas);
                unset(
                    $GLOBALS['__sims_services_file_exists'],
                    $GLOBALS['__sims_services_mime_types'],
                    $GLOBALS['__sims_services_imagecreatefromwebp_exists']
                );
            }

            self::assertSame(200, imagesx($canvas));
        }
    }
}
