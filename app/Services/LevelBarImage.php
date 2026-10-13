<?php

namespace App\Services;

/**
 * Draws the percentage, phrase, bar fill and lesson count on top of the
 * pre-made band template (resources/images/barra_template_nivel_{1..4}.png).
 */
class LevelBarImage
{
    private const FONT_BOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    private const BAR_X = 64;

    private const BAR_Y = 284;

    private const BAR_WIDTH = 672;

    private const BAR_HEIGHT = 33;

    private const BAR_RADIUS = 16;

    private const CANVAS_WIDTH = 800;

    public function generate(int $level, string $band, string $colorHex, string $phrase, int $answeredCount, int $totalLessons): string
    {
        $level = max(0, min(100, $level));
        $templateNumber = (int) substr($band, -1);
        $templatePath = resource_path("images/barra_template_nivel_{$templateNumber}.png");

        $image = imagecreatefrompng($templatePath);
        imagesavealpha($image, true);
        imagealphablending($image, true);

        [$r, $g, $b] = $this->hexToRgb($colorHex);
        $accent = imagecolorallocate($image, $r, $g, $b);
        $phraseColor = imagecolorallocate($image, 43, 34, 51);
        $countColor = imagecolorallocate($image, 115, 106, 98);

        // Bar fill, from the same left edge as the (already drawn) empty track.
        $fillWidth = (int) round(self::BAR_WIDTH * ($level / 100));
        if ($level > 0) {
            $fillWidth = max($fillWidth, 34);
        }

        if ($fillWidth > 0) {
            $this->roundedRect(
                $image,
                self::BAR_X,
                self::BAR_Y,
                self::BAR_X + min($fillWidth, self::BAR_WIDTH),
                self::BAR_Y + self::BAR_HEIGHT,
                self::BAR_RADIUS,
                $accent,
            );
        }

        // Big percentage number, left-aligned under the "Твой уровень" label.
        imagettftext($image, 58, 0, 350, 181, $accent, self::FONT_BOLD, "{$level}%");

        // Phrase, bold and dark, below the percentage. Emoji stripped: the
        // bold sans font has no glyphs for them and renders garbage bytes.
        $imageSafePhrase = trim(preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}]/u', '', $phrase));
        imagettftext($image, 24, 0, 347, 240, $phraseColor, self::FONT_BOLD, $imageSafePhrase);

        // "Отвечено уроков: X из 50", centered under the bar.
        $countText = "Отвечено уроков: {$answeredCount} из {$totalLessons}";
        $box = imagettfbbox(16, 0, self::FONT_BOLD, $countText);
        $textWidth = $box[2] - $box[0];
        $countX = (int) ((self::CANVAS_WIDTH - $textWidth) / 2);
        imagettftext($image, 16, 0, $countX, 367, $countColor, self::FONT_BOLD, $countText);

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        return $binary;
    }

    private function roundedRect($image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
