<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StudentRepository;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\BarcodeGeneratorPNG;
use RuntimeException;

final class IdCardService
{
    private const CARD_WIDTH = 1080;

    private const CARD_HEIGHT = 680;

    public function __construct(
        private readonly StudentRepository $students,
        private readonly string $outputPath,
        private readonly string $appUrl
    ) {
    }

    /**
     * @return array{file_name:string, absolute_path:string, student:StudentRow}
     */
    public function generate(int $studentId, ?int $generatedBy = null): array
    {
        $student = $this->students->find($studentId);

        if ($student === null) {
            throw new RuntimeException('Student not found.');
        }

        $studentNumber = $student['student_number'];
        $verificationUrl = rtrim($this->appUrl, '/') . sprintf('/id-cards/%d/verify', $studentId);
        $qrPayload = json_encode([
            'student_number' => $studentNumber,
            'verify' => $verificationUrl,
        ], JSON_THROW_ON_ERROR);

        $barcodePayload = $studentNumber;

        $canvas = $this->createCanvas();
        $this->drawBaseCard($canvas, $student);
        $this->drawQrCode($canvas, $qrPayload);
        $this->drawBarcode($canvas, $barcodePayload);

        $filename = sprintf('student-id-%d.png', $studentId);
        $absolutePath = $this->outputPath . '/' . $filename;
        imagepng($canvas, $absolutePath);
        imagedestroy($canvas);

        $this->students->saveIdCard($studentId, $filename, $qrPayload, $barcodePayload, $generatedBy);

        return [
            'file_name' => $filename,
            'absolute_path' => $absolutePath,
            'student' => $student,
        ];
    }

    /**
     * @return array{student_id:int, file_path:string, qr_payload:string, barcode_payload:string, generated_by:int|null, generated_at:string}|null
     */
    public function latestCard(int $studentId): ?array
    {
        return $this->students->latestIdCard($studentId);
    }

    private function createCanvas(): \GdImage
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD extension is required for ID generation.');
        }

        $canvas = imagecreatetruecolor(self::CARD_WIDTH, self::CARD_HEIGHT);
        imageantialias($canvas, true);
        imagesavealpha($canvas, false);

        $background = $this->allocateColor($canvas, 248, 251, 255);
        imagefilledrectangle($canvas, 0, 0, self::CARD_WIDTH, self::CARD_HEIGHT, $background);

        return $canvas;
    }

    /**
     * @param StudentRow $student
     */
    private function drawBaseCard(\GdImage $canvas, array $student): void
    {
        $navy = $this->allocateColor($canvas, 24, 54, 88);
        $navyDeep = $this->allocateColor($canvas, 15, 35, 58);
        $royal = $this->allocateColor($canvas, 53, 87, 164);
        $royalMuted = $this->allocateColor($canvas, 72, 103, 184);
        $gold = $this->allocateColor($canvas, 236, 179, 50);
        $white = $this->allocateColor($canvas, 255, 255, 255);
        $ink = $this->allocateColor($canvas, 28, 43, 59);
        $muted = $this->allocateColor($canvas, 101, 117, 135);
        $softSurface = $this->allocateColor($canvas, 244, 247, 251);
        $panelBorder = $this->allocateColor($canvas, 221, 229, 237);
        $footerText = $this->allocateColor($canvas, 217, 228, 240);

        $this->fillRoundedRectangle($canvas, 20, 20, self::CARD_WIDTH - 20, self::CARD_HEIGHT - 20, 30, $white);
        $this->drawRoundedBorder($canvas, 20, 20, self::CARD_WIDTH - 20, self::CARD_HEIGHT - 20, 30, $panelBorder, 2);

        imagefilledrectangle($canvas, 20, 20, self::CARD_WIDTH - 20, 160, $navyDeep);
        imagefilledrectangle($canvas, 20, 160, 298, self::CARD_HEIGHT - 20, $royal);
        imagefilledrectangle($canvas, 298, 160, self::CARD_WIDTH - 20, self::CARD_HEIGHT - 20, $softSurface);

        imagefilledellipse($canvas, 960, 88, 78, 78, $this->allocateColor($canvas, 232, 239, 247));
        $this->drawText($canvas, 'BC', 23, 960, 96, $navy, true, 'center');

        $this->drawText($canvas, 'Bestlink College', 24, 34, 52, $white, true);
        $this->drawText($canvas, 'of the Philippines', 18, 34, 82, $this->allocateColor($canvas, 233, 240, 248), true);
        $this->drawText($canvas, 'Official student identification card', 11, 34, 110, $this->allocateColor($canvas, 214, 225, 238), false);
        $this->drawText($canvas, 'Student lifecycle and registrar operations', 10, 34, 130, $this->allocateColor($canvas, 180, 196, 214), false);

        $this->fillRoundedRectangle($canvas, 316, 186, 538, 248, 18, $royalMuted);
        $this->drawText($canvas, strtoupper((string) ($student['department'] ?? 'PROGRAM')), 24, 342, 225, $white, true);
        $this->drawText($canvas, $this->truncate((string) ($student['program'] ?? ''), 28), 10, 342, 244, $this->allocateColor($canvas, 228, 235, 247), false);

        $this->drawText($canvas, 'Academic Year', 10, 742, 66, $this->allocateColor($canvas, 192, 207, 223), true);
        $this->drawText($canvas, $this->academicYearLabel(), 18, 742, 94, $white, true);
        $this->drawText($canvas, 'Student Number', 10, 742, 120, $this->allocateColor($canvas, 192, 207, 223), true);
        $this->drawText($canvas, (string) ($student['student_number'] ?? ''), 17, 742, 146, $white, true);

        $this->fillRoundedRectangle($canvas, 52, 188, 266, 446, 26, $white);
        $this->drawRoundedBorder($canvas, 52, 188, 266, 446, 26, $gold, 4);
        $this->drawStudentPhoto($canvas, $student, 66, 202, 186, 230, $white, $muted, $royalMuted);

        $this->fillRoundedRectangle($canvas, 316, 278, 1016, 410, 22, $white);
        $this->drawRoundedBorder($canvas, 316, 278, 1016, 410, 22, $panelBorder, 2);
        $this->drawText($canvas, $this->truncate(trim((string) (($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))), 28), 32, 350, 326, $ink, true);
        $this->drawText($canvas, (string) ($student['student_number'] ?? ''), 18, 350, 356, $navy, true);
        $this->drawText($canvas, $this->truncate((string) ($student['program'] ?? ''), 42), 13, 350, 382, $muted, false);
        $this->drawText($canvas, 'Year ' . ($student['year_level'] ?? '') . ' • ' . ($student['department'] ?? ''), 13, 350, 404, $muted, true);

        $this->fillRoundedRectangle($canvas, 316, 430, 774, 548, 22, $white);
        $this->drawRoundedBorder($canvas, 316, 430, 774, 548, 22, $panelBorder, 2);
        $this->drawText($canvas, 'Student Number Barcode', 11, 346, 462, $muted, true);

        $this->fillRoundedRectangle($canvas, 804, 430, 1016, 626, 22, $white);
        $this->drawRoundedBorder($canvas, 804, 430, 1016, 626, 22, $panelBorder, 2);
        $this->drawText($canvas, 'Verification QR', 11, 832, 462, $muted, true);

        $this->fillRoundedRectangle($canvas, 52, 566, 760, 628, 24, $navy);
        $this->drawText($canvas, $this->truncate(trim((string) (($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))), 26), 24, 84, 604, $white, true);
        $this->drawText($canvas, $this->truncate((string) ($student['program'] ?? ''), 46), 12, 84, 624, $footerText, false);
        $this->drawText($canvas, strtoupper((string) ($student['enrollment_status'] ?? 'Active')), 12, 728, 592, $gold, true, 'right');
        $this->drawText($canvas, strtoupper((string) ($student['latest_status'] ?? 'Pending')), 12, 728, 614, $footerText, true, 'right');
    }

    /**
     * @param StudentRow $student
     */
    private function drawStudentPhoto(
        \GdImage $canvas,
        array $student,
        int $x,
        int $y,
        int $targetWidth,
        int $targetHeight,
        int $placeholderBackground,
        int $placeholderText,
        int $placeholderAccent
    ): void {
        $photoPath = (string) ($student['photo_path'] ?? '');
        $absolutePath = dirname(__DIR__, 2) . '/storage/app/private/uploads/' . $photoPath;
        if ($photoPath === '' || !file_exists($absolutePath)) {
            imagefilledrectangle($canvas, $x, $y, $x + $targetWidth, $y + $targetHeight, $placeholderBackground);
            imagefilledellipse(
                $canvas,
                $x + (int) ($targetWidth / 2),
                $y + (int) ($targetHeight / 2) - 18,
                92,
                92,
                $placeholderAccent
            );
            $initials = strtoupper(substr((string) ($student['first_name'] ?? 'S'), 0, 1) . substr((string) ($student['last_name'] ?? 'T'), 0, 1));
            $this->drawText($canvas, $initials, 24, $x + (int) ($targetWidth / 2), $y + (int) ($targetHeight / 2) - 8, $this->allocateColor($canvas, 255, 255, 255), true, 'center');
            $this->drawText($canvas, 'Photo on file pending', 11, $x + (int) ($targetWidth / 2), $y + (int) ($targetHeight / 2) + 52, $placeholderText, false, 'center');
            return;
        }

        $mime = mime_content_type($absolutePath);
        $photo = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($absolutePath),
            'image/png' => imagecreatefrompng($absolutePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($absolutePath) : false,
            default => false,
        };

        if (!$photo instanceof \GdImage) {
            return;
        }

        $sourceWidth = imagesx($photo);
        $sourceHeight = imagesy($photo);
        $sourceRatio = $sourceWidth / max($sourceHeight, 1);
        $targetRatio = $targetWidth / max($targetHeight, 1);

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
            $cropY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $cropX = 0;
            $cropY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        imagecopyresampled(
            $canvas,
            $photo,
            $x,
            $y,
            $cropX,
            $cropY,
            $targetWidth,
            $targetHeight,
            $cropWidth,
            $cropHeight
        );
        imagedestroy($photo);
    }

    private function drawQrCode(\GdImage $canvas, string $payload): void
    {
        $qrRenderer = new QRCode(new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'returnResource' => true,
            'scale' => 8,
        ]));
        $qrImage = $qrRenderer->render($payload);

        if ($qrImage instanceof \GdImage) {
            imagecopyresampled($canvas, $qrImage, 852, 480, 0, 0, 116, 116, imagesx($qrImage), imagesy($qrImage));
            imagedestroy($qrImage);
        }
    }

    private function drawBarcode(\GdImage $canvas, string $payload): void
    {
        $generator = new BarcodeGeneratorPNG();
        $barcode = $generator->getBarcode($payload, 'C128');
        $barcodeImage = imagecreatefromstring($barcode);

        if ($barcodeImage instanceof \GdImage) {
            imagecopyresampled($canvas, $barcodeImage, 348, 474, 0, 0, 394, 48, imagesx($barcodeImage), imagesy($barcodeImage));
            imagedestroy($barcodeImage);
        }

        $ink = $this->allocateColor($canvas, 34, 49, 63);
        $muted = $this->allocateColor($canvas, 90, 104, 120);
        $this->drawText($canvas, $payload, 15, 545, 538, $ink, true, 'center');
        $this->drawText($canvas, 'Scan for record verification', 10, 910, 614, $muted, false, 'center');
    }

    private function drawText(
        \GdImage $canvas,
        string $text,
        int $size,
        int $x,
        int $y,
        int $color,
        bool $bold = false,
        string $align = 'left'
    ): void {
        $font = $this->fontPath($bold);

        if ($font !== null && function_exists('imagettftext')) {
            $bounds = imagettfbbox($size, 0, $font, $text);
            if (is_array($bounds)) {
                /** @var array{0: float|int, 2: float|int} $bounds */
                $width = (int) abs($bounds[2] - $bounds[0]);
                if ($align === 'center') {
                    $x -= (int) round($width / 2);
                } elseif ($align === 'right') {
                    $x -= $width;
                }
            }

            imagettftext($canvas, $size, 0, $x, $y, $color, $font, $text);

            return;
        }

        imagestring($canvas, $bold ? 5 : 4, $x, $y - 14, $text, $color);
    }

    private function fontPath(bool $bold): ?string
    {
        $candidates = $bold
            ? [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            ]
            : [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function fillRoundedRectangle(\GdImage $canvas, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($canvas, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($canvas, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($canvas, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($canvas, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    private function drawRoundedBorder(\GdImage $canvas, int $x1, int $y1, int $x2, int $y2, int $radius, int $color, int $thickness): void
    {
        imagesetthickness($canvas, $thickness);
        imageline($canvas, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
        imageline($canvas, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
        imageline($canvas, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
        imageline($canvas, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagearc($canvas, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color);
        imagearc($canvas, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color);
        imagearc($canvas, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color);
        imagearc($canvas, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color);
        imagesetthickness($canvas, 1);
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $maxLength - 1)) . '…';
    }

    private function academicYearLabel(): string
    {
        $year = (int) date('Y');

        return sprintf('%d-%d', $year, $year + 1);
    }

    /**
     * @param int<0, 255> $red
     * @param int<0, 255> $green
     * @param int<0, 255> $blue
     */
    private function allocateColor(\GdImage $canvas, int $red, int $green, int $blue): int
    {
        $color = imagecolorallocate($canvas, $red, $green, $blue);

        if ($color === false) {
            throw new RuntimeException('Unable to allocate ID card color.');
        }

        return $color;
    }
}
