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
        $navy = $this->allocateColor($canvas, 19, 40, 72);
        $navyDeep = $this->allocateColor($canvas, 11, 28, 53);
        $royal = $this->allocateColor($canvas, 28, 92, 182);
        $royalSoft = $this->allocateColor($canvas, 68, 111, 196);
        $gold = $this->allocateColor($canvas, 237, 165, 33);
        $white = $this->allocateColor($canvas, 255, 255, 255);
        $ink = $this->allocateColor($canvas, 31, 45, 66);
        $muted = $this->allocateColor($canvas, 108, 124, 143);
        $panelBorder = $this->allocateColor($canvas, 215, 224, 233);
        $surface = $this->allocateColor($canvas, 246, 249, 252);
        $blueText = $this->allocateColor($canvas, 214, 228, 245);
        $teal = $this->allocateColor($canvas, 41, 128, 120);
        $green = $this->allocateColor($canvas, 40, 167, 69);
        $red = $this->allocateColor($canvas, 218, 48, 55);

        $this->fillRoundedRectangle($canvas, 24, 24, self::CARD_WIDTH - 24, self::CARD_HEIGHT - 24, 30, $white);
        $this->drawRoundedBorder($canvas, 24, 24, self::CARD_WIDTH - 24, self::CARD_HEIGHT - 24, 30, $panelBorder, 2);

        imagefilledrectangle($canvas, 42, 42, self::CARD_WIDTH - 42, 166, $navyDeep);
        imagefilledrectangle($canvas, 42, 166, 292, self::CARD_HEIGHT - 42, $royalSoft);
        imagefilledrectangle($canvas, 292, 166, self::CARD_WIDTH - 42, self::CARD_HEIGHT - 42, $surface);
        imagefilledrectangle($canvas, 42, 166, self::CARD_WIDTH - 42, 174, $gold);
        imagefilledpolygon($canvas, [874, 42, 1038, 42, 1038, 160], $red);
        imagefilledpolygon($canvas, [904, 42, 1038, 42, 1038, 120], $white);

        $this->drawText($canvas, 'Bestlink College', 23, 74, 82, $white, true);
        $this->drawText($canvas, 'of the Philippines', 18, 74, 108, $blueText, true);
        $this->drawText($canvas, 'Official student identification card', 11, 74, 132, $this->allocateColor($canvas, 190, 204, 219), false);
        $this->drawText($canvas, 'Student lifecycle and registrar operations', 10, 74, 150, $this->allocateColor($canvas, 176, 191, 210), false);

        $this->drawText($canvas, 'Academic Year', 11, 724, 78, $blueText, true);
        $this->drawText($canvas, $this->academicYearLabel(), 18, 724, 106, $white, true);
        $this->drawText($canvas, 'Student Number', 11, 724, 132, $blueText, true);
        $this->drawText($canvas, (string) ($student['student_number'] ?? ''), 18, 724, 156, $white, true);

        $this->drawText($canvas, '2002', 15, 988, 78, $navyDeep, true, 'center');
        $this->drawInstitutionLogo($canvas, 914, 80, 74, 78);

        $this->fillRoundedRectangle($canvas, 74, 202, 252, 456, 24, $white);
        $this->drawRoundedBorder($canvas, 74, 202, 252, 456, 24, $gold, 4);
        $this->drawStudentPhoto($canvas, $student, 86, 214, 154, 230, $white, $muted, $royal);

        $this->fillRoundedRectangle($canvas, 322, 202, 566, 268, 18, $royal);
        $this->drawText($canvas, strtoupper($this->truncate((string) ($student['department'] ?? 'PROGRAM'), 16)), 22, 350, 238, $white, true);
        $this->drawText($canvas, $this->truncate((string) ($student['program'] ?? ''), 34), 10, 350, 256, $this->allocateColor($canvas, 226, 235, 248), false);

        $this->fillRoundedRectangle($canvas, 322, 294, 982, 410, 22, $white);
        $this->drawRoundedBorder($canvas, 322, 294, 982, 410, 22, $panelBorder, 2);
        $this->drawText($canvas, $this->truncate(trim((string) (($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))), 30), 30, 352, 330, $ink, true);
        $this->drawText($canvas, (string) ($student['student_number'] ?? ''), 17, 352, 358, $navy, true);
        $this->drawText($canvas, $this->truncate((string) ($student['program'] ?? ''), 44), 13, 352, 382, $muted, false);
        $this->drawText($canvas, 'Year ' . ($student['year_level'] ?? '') . ' - ' . ($student['department'] ?? ''), 13, 352, 398, $muted, false);

        $this->fillRoundedRectangle($canvas, 322, 436, 748, 576, 20, $white);
        $this->drawRoundedBorder($canvas, 322, 436, 748, 576, 20, $panelBorder, 2);
        $this->drawText($canvas, 'Student Number Barcode', 11, 352, 466, $muted, true);

        $this->fillRoundedRectangle($canvas, 776, 436, 982, 576, 20, $white);
        $this->drawRoundedBorder($canvas, 776, 436, 982, 576, 20, $panelBorder, 2);
        $this->drawText($canvas, 'Verification QR', 11, 806, 466, $muted, true);

        $enrollmentLabel = strtoupper((string) ($student['enrollment_status'] ?? 'Active'));
        $workflowLabel = strtoupper((string) ($student['latest_status'] ?? 'Pending'));
        $footerLayout = $this->footerBadgeLayout($enrollmentLabel, $workflowLabel);

        $this->fillRoundedRectangle(
            $canvas,
            $footerLayout['enrollment_badge_x1'],
            $footerLayout['badge_top'],
            $footerLayout['enrollment_badge_x2'],
            $footerLayout['badge_top'] + $footerLayout['badge_height'],
            $footerLayout['badge_radius'],
            $green
        );
        $this->fillRoundedRectangle(
            $canvas,
            $footerLayout['workflow_badge_x1'],
            $footerLayout['badge_top'],
            $footerLayout['workflow_badge_x2'],
            $footerLayout['badge_top'] + $footerLayout['badge_height'],
            $footerLayout['badge_radius'],
            $teal
        );
        $this->drawText(
            $canvas,
            $enrollmentLabel,
            $footerLayout['badge_label_size'],
            $footerLayout['enrollment_label_center_x'],
            611,
            $white,
            true,
            'center'
        );
        $this->drawText(
            $canvas,
            $workflowLabel,
            $footerLayout['badge_label_size'],
            $footerLayout['workflow_label_center_x'],
            611,
            $white,
            true,
            'center'
        );

        $this->fillRoundedRectangle($canvas, 74, 588, $footerLayout['footer_right'], 628, 20, $navy);
        $this->drawText($canvas, $this->truncate(trim((string) (($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))), 28), 20, 102, 610, $white, true);
        $this->drawText($canvas, $this->truncate((string) ($student['program'] ?? ''), 48), 11, 102, 625, $this->allocateColor($canvas, 217, 227, 238), false);

        imagefilledrectangle($canvas, 42, self::CARD_HEIGHT - 52, self::CARD_WIDTH - 42, self::CARD_HEIGHT - 42, $navyDeep);
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
            imagecopyresampled($canvas, $qrImage, 839, 474, 0, 0, 80, 80, imagesx($qrImage), imagesy($qrImage));
            imagedestroy($qrImage);
        }

        $muted = $this->allocateColor($canvas, 90, 104, 120);
        $this->drawText($canvas, 'Scan to verify', 8, 879, 566, $muted, false, 'center');
    }

    private function drawBarcode(\GdImage $canvas, string $payload): void
    {
        $generator = new BarcodeGeneratorPNG();
        $barcode = $generator->getBarcode($payload, 'C128');
        $barcodeImage = imagecreatefromstring($barcode);

        if ($barcodeImage instanceof \GdImage) {
            imagecopyresampled($canvas, $barcodeImage, 350, 486, 0, 0, 370, 44, imagesx($barcodeImage), imagesy($barcodeImage));
            imagedestroy($barcodeImage);
        }

        $ink = $this->allocateColor($canvas, 34, 49, 63);
        $this->drawText($canvas, $payload, 14, 535, 556, $ink, true, 'center');
    }

    private function drawInstitutionLogo(\GdImage $canvas, int $x, int $y, int $targetWidth, int $targetHeight): void
    {
        $logoPath = dirname(__DIR__, 2) . '/public/assets/branding/bcp-logo.png';

        if (!file_exists($logoPath)) {
            return;
        }

        $logo = @imagecreatefrompng($logoPath);

        if (!$logo instanceof \GdImage) {
            return;
        }

        imagealphablending($canvas, true);
        imagesavealpha($logo, true);

        $sourceWidth = imagesx($logo);
        $sourceHeight = imagesy($logo);
        $scale = min($targetWidth / max($sourceWidth, 1), $targetHeight / max($sourceHeight, 1));
        $renderWidth = (int) round($sourceWidth * $scale);
        $renderHeight = (int) round($sourceHeight * $scale);
        $offsetX = $x + (int) floor(($targetWidth - $renderWidth) / 2);
        $offsetY = $y + (int) floor(($targetHeight - $renderHeight) / 2);

        imagecopyresampled(
            $canvas,
            $logo,
            $offsetX,
            $offsetY,
            0,
            0,
            $renderWidth,
            $renderHeight,
            $sourceWidth,
            $sourceHeight
        );

        imagedestroy($logo);
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
            $width = $this->measureTextWidth($text, $size, $bold);
            if ($align === 'center') {
                $x -= (int) round($width / 2);
            } elseif ($align === 'right') {
                $x -= $width;
            }

            imagettftext($canvas, $size, 0, $x, $y, $color, $font, $text);

            return;
        }

        imagestring($canvas, $bold ? 5 : 4, $x, $y - 14, $text, $color);
    }

    private function measureTextWidth(string $text, int $size, bool $bold): int
    {
        $font = $this->fontPath($bold);

        if ($font !== null && function_exists('imagettfbbox')) {
            $bounds = imagettfbbox($size, 0, $font, $text);
            if (is_array($bounds)) {
                /** @var array{0: float|int, 2: float|int} $bounds */
                return (int) abs($bounds[2] - $bounds[0]);
            }
        }

        return imagefontwidth($bold ? 5 : 4) * strlen($text);
    }

    /**
     * @return array{
     *     badge_height:int,
     *     badge_label_size:int,
     *     badge_gap:int,
     *     badge_radius:int,
     *     badge_right:int,
     *     badge_top:int,
     *     enrollment_badge_x1:int,
     *     enrollment_badge_x2:int,
     *     enrollment_badge_width:int,
     *     enrollment_label_center_x:int,
     *     footer_right:int,
     *     gap_after_footer:int,
     *     right_margin:int,
     *     workflow_badge_x1:int,
     *     workflow_badge_x2:int,
     *     workflow_badge_width:int,
     *     workflow_label_center_x:int
     * }
     */
    private function footerBadgeLayout(string $enrollmentLabel, string $workflowLabel): array
    {
        $badgeHeight = 32;
        $badgeLabelSize = 8;
        $badgeRadius = 14;
        $badgeGap = 18;
        $badgeRight = self::CARD_WIDTH - 130;
        $enrollmentBadgeWidth = 118;
        $workflowBadgeWidth = 140;

        $workflowBadgeX2 = $badgeRight;
        $workflowBadgeX1 = $workflowBadgeX2 - $workflowBadgeWidth;
        $enrollmentBadgeX2 = $workflowBadgeX1 - $badgeGap;
        $enrollmentBadgeX1 = $enrollmentBadgeX2 - $enrollmentBadgeWidth;
        $gapAfterFooter = 24;
        $footerRight = max(660, $enrollmentBadgeX1 - $gapAfterFooter);

        return [
            'badge_height' => $badgeHeight,
            'badge_label_size' => $badgeLabelSize,
            'badge_gap' => $badgeGap,
            'badge_radius' => $badgeRadius,
            'badge_right' => $badgeRight,
            'badge_top' => 590,
            'enrollment_badge_x1' => $enrollmentBadgeX1,
            'enrollment_badge_x2' => $enrollmentBadgeX2,
            'enrollment_badge_width' => $enrollmentBadgeWidth,
            'enrollment_label_center_x' => $enrollmentBadgeX1 + (int) round($enrollmentBadgeWidth / 2),
            'footer_right' => $footerRight,
            'gap_after_footer' => $gapAfterFooter,
            'right_margin' => self::CARD_WIDTH - $badgeRight,
            'workflow_badge_x1' => $workflowBadgeX1,
            'workflow_badge_x2' => $workflowBadgeX2,
            'workflow_badge_width' => $workflowBadgeWidth,
            'workflow_label_center_x' => $workflowBadgeX1 + (int) round($workflowBadgeWidth / 2),
        ];
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
