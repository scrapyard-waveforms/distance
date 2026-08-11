<?php

namespace Waveforms\Distance\Runner\Sketches\Demos\Concerns;

use ScrapyardIO\Tubes\Canvas\Canvas;
use ScrapyardIO\Tubes\Rendering\Renderer2D;

/**
 * Visual distance stage — every label is measured and fitted to its layout band.
 */
trait PaintsTubesDistanceHud
{
    /** @var int Chip MinDistance (mm) — filled from Rangefinder after boot. */
    protected int $rangeMinMm = 0;

    /** @var int Chip MaxDistance (mm) — filled from Rangefinder after boot. */
    protected int $rangeMaxMm = 400;

    protected function paintDistanceHud(Renderer2D $renderer, Canvas $canvas, int $mm): void
    {
        $w = max(1, $canvas->width());
        $h = max(1, $canvas->height());
        $bg = 0x0A0C10FF;
        $fg = 0xF2F5F8FF;
        $muted = 0x8B93A1FF;
        $accent = 0x3DDC97FF;
        $warn = 0xFFB020FF;
        $track = 0x1E2430FF;

        $fb = $canvas->framebuffer();
        $renderer->setFramebuffer($fb);
        $renderer->fill($bg);

        // Default 6×8 face only — custom fonts break size estimates and overflow.
        $renderer->setFont(null)->setTextWrap(false);

        $pct = $this->rangePercent($mm);
        $prompt = $this->proximityPrompt($mm);
        $promptColor = $this->promptColor($mm, $accent, $warn, $muted);

        if ($h < 100 || $w < 160) {
            $this->paintCompactHud($renderer, $w, $h, $mm, $pct, $prompt, $fg, $bg, $promptColor, $track, $accent);

            return;
        }

        $this->paintStageHud($renderer, $w, $h, $mm, $pct, $prompt, $fg, $bg, $muted, $promptColor, $track, $accent);
    }

    protected function rangePercent(int $mm): float
    {
        if ($mm < 0) {
            return 0.0;
        }

        $span = max(1, $this->rangeMaxMm - $this->rangeMinMm);

        return max(0.0, min(1.0, ($mm - $this->rangeMinMm) / $span));
    }

    protected function proximityPrompt(int $mm): string
    {
        if ($mm < 0) {
            return 'Waiting for a reading…';
        }

        $pct = $this->rangePercent($mm);

        if ($pct <= 0.20) {
            return 'Pull your hand away from the sensor';
        }

        if ($pct <= 0.55) {
            return 'Move your hand closer — or farther away';
        }

        return 'Place your hand over the sensor';
    }

    protected function promptColor(int $mm, int $accent, int $warn, int $muted): int
    {
        if ($mm < 0) {
            return $muted;
        }

        $pct = $this->rangePercent($mm);

        if ($pct <= 0.20) {
            return $warn;
        }

        if ($pct <= 0.55) {
            return $muted;
        }

        return $accent;
    }

    protected function paintStageHud(
        Renderer2D $renderer,
        int $w,
        int $h,
        int $mm,
        float $pct,
        string $prompt,
        int $fg,
        int $bg,
        int $muted,
        int $promptColor,
        int $track,
        int $accent,
    ): void {
        $marginX = max(16, (int) round($w * 0.05));
        $marginY = max(12, (int) round($h * 0.04));
        $innerW = max(1, $w - (2 * $marginX));
        $contentTop = $marginY;
        $contentBottom = $h - $marginY;

        // Vertical bands as fractions of usable height (must sum ≤ 1).
        $usable = max(1, $contentBottom - $contentTop);
        $titleH = (int) round($usable * 0.10);
        $valueH = (int) round($usable * 0.42);
        $unitH = (int) round($usable * 0.08);
        $promptH = (int) round($usable * 0.14);
        $legendH = (int) round($usable * 0.06);
        $barH = max(12, min(28, (int) round($usable * 0.08)));

        $y = $contentTop;

        $this->paintFittedCentered(
            $renderer,
            'RANGEFINDER',
            $marginX,
            $y,
            $innerW,
            $titleH,
            $muted,
            $bg,
            1,
            4,
        );
        $y += $titleH;

        $value = $mm < 0 ? '—' : (string) $mm;
        $this->paintFittedCentered(
            $renderer,
            $value,
            $marginX,
            $y,
            $innerW,
            $valueH,
            $fg,
            $bg,
            2,
            48,
        );
        $y += $valueH;

        $this->paintFittedCentered(
            $renderer,
            'millimetres',
            $marginX,
            $y,
            $innerW,
            $unitH,
            $accent,
            $bg,
            1,
            6,
        );
        $y += $unitH;

        $this->paintFittedCentered(
            $renderer,
            $prompt,
            $marginX,
            $y,
            $innerW,
            $promptH,
            $promptColor,
            $bg,
            1,
            5,
        );

        $barY = $contentBottom - $barH;
        $legendY = $barY - $legendH;
        $barMaxW = $innerW;
        $barW = max(1, (int) round($barMaxW * $pct));

        $nearLabel = 'NEAR '.$this->rangeMinMm.'mm';
        $farLabel = $this->rangeMaxMm.'mm FAR';
        $this->paintFittedLeft($renderer, $nearLabel, $marginX, $legendY, (int) ($innerW * 0.45), $legendH, $muted, $bg, 1, 3);
        $this->paintFittedRight($renderer, $farLabel, $marginX, $legendY, $innerW, $legendH, $muted, $bg, 1, 3);

        $renderer->fillRect($marginX, $barY, $barMaxW, $barH, $track);
        $renderer->fillRect($marginX, $barY, $barW, $barH, $accent);

        $renderer->setFont(null);
    }

    protected function paintCompactHud(
        Renderer2D $renderer,
        int $w,
        int $h,
        int $mm,
        float $pct,
        string $prompt,
        int $fg,
        int $bg,
        int $promptColor,
        int $track,
        int $accent,
    ): void {
        $value = $mm < 0 ? '—' : sprintf('%d mm', $mm);
        $valueH = max(8, (int) ($h * 0.50));
        $this->paintFittedCentered($renderer, $value, 2, 2, $w - 4, $valueH, $fg, $bg, 1, 6);

        $short = match (true) {
            str_contains($prompt, 'Pull') => 'pull away',
            str_contains($prompt, 'Move') => 'move hand',
            str_contains($prompt, 'Waiting') => 'wait…',
            default => 'cover sensor',
        };
        $promptTop = 2 + $valueH;
        $promptH = max(8, (int) ($h * 0.28));
        $this->paintFittedCentered($renderer, $short, 2, $promptTop, $w - 4, $promptH, $promptColor, $bg, 1, 2);

        $barH = max(3, min(8, $h - $promptTop - $promptH - 2));
        $barY = $h - $barH - 1;
        $barMaxW = max(1, $w - 4);
        $barW = max(1, (int) round($barMaxW * $pct));
        $renderer->fillRect(2, $barY, $barMaxW, $barH, $track);
        $renderer->fillRect(2, $barY, $barW, $barH, $accent);
        $renderer->setFont(null);
    }

    /**
     * Shrink textSize until measured bounds fit the box, then draw centered in that box.
     */
    protected function paintFittedCentered(
        Renderer2D $renderer,
        string $text,
        int $boxX,
        int $boxY,
        int $boxW,
        int $boxH,
        int $fg,
        int $bg,
        int $minSize,
        int $maxSize,
    ): void {
        if ($text === '' || $boxW < 1 || $boxH < 1) {
            return;
        }

        $size = $this->fitTextSize($renderer, $text, $boxW, $boxH, $minSize, $maxSize);
        [$textW, $textH] = $this->measureText($renderer, $text, $size);
        $x = $boxX + max(0, (int) (($boxW - $textW) / 2));
        $y = $boxY + max(0, (int) (($boxH - $textH) / 2));

        $renderer->setTextSize($size)
            ->setTextColor($fg, $bg)
            ->setCursor($x, $y)
            ->println($text);
    }

    protected function paintFittedLeft(
        Renderer2D $renderer,
        string $text,
        int $boxX,
        int $boxY,
        int $boxW,
        int $boxH,
        int $fg,
        int $bg,
        int $minSize,
        int $maxSize,
    ): void {
        if ($text === '' || $boxW < 1 || $boxH < 1) {
            return;
        }

        $size = $this->fitTextSize($renderer, $text, $boxW, $boxH, $minSize, $maxSize);
        [, $textH] = $this->measureText($renderer, $text, $size);
        $y = $boxY + max(0, (int) (($boxH - $textH) / 2));

        $renderer->setTextSize($size)
            ->setTextColor($fg, $bg)
            ->setCursor($boxX, $y)
            ->println($text);
    }

    protected function paintFittedRight(
        Renderer2D $renderer,
        string $text,
        int $boxX,
        int $boxY,
        int $boxW,
        int $boxH,
        int $fg,
        int $bg,
        int $minSize,
        int $maxSize,
    ): void {
        if ($text === '' || $boxW < 1 || $boxH < 1) {
            return;
        }

        $size = $this->fitTextSize($renderer, $text, $boxW, $boxH, $minSize, $maxSize);
        [$textW, $textH] = $this->measureText($renderer, $text, $size);
        $x = $boxX + max(0, $boxW - $textW);
        $y = $boxY + max(0, (int) (($boxH - $textH) / 2));

        $renderer->setTextSize($size)
            ->setTextColor($fg, $bg)
            ->setCursor($x, $y)
            ->println($text);
    }

    protected function fitTextSize(
        Renderer2D $renderer,
        string $text,
        int $maxW,
        int $maxH,
        int $min,
        int $max,
    ): int {
        $min = max(1, $min);
        $max = max($min, $max);
        $lo = $min;
        $hi = $max;
        $best = $min;

        while ($lo <= $hi) {
            $mid = (int) (($lo + $hi) / 2);
            [$tw, $th] = $this->measureText($renderer, $text, $mid);
            if ($tw <= $maxW && $th <= $maxH) {
                $best = $mid;
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        return $best;
    }

    /**
     * Classic 6×8 cell metrics (we force setFont(null) for these demos).
     *
     * @return array{0: int, 1: int} width, height
     */
    protected function measureText(Renderer2D $renderer, string $text, int $size): array
    {
        $renderer->setTextSize($size)->setTextWrap(false);

        return [
            max(1, strlen($text) * 6 * $size),
            max(1, 8 * $size),
        ];
    }
}
