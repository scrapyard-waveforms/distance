<?php

namespace Waveforms\Distance\Runner\Sketches\Demos\Assets;

use ScrapyardIO\UX\Components\Indicators\ProgressBar;
use ScrapyardIO\UX\Components\Text\Label;
use ScrapyardIO\UX\Core\PaintContext;
use ScrapyardIO\UX\Core\UIComponent;
use ScrapyardIO\UX\Enums\Axis;
use ScrapyardIO\UX\Enums\TextAlign;
use ScrapyardIO\UX\Geometry\Size;
use ScrapyardIO\UX\Support\Theme;

/**
 * Full-canvas proximity stage — labels sized to fit measured layout bands.
 */
class RangeHud extends UIComponent
{
    protected Label $title;

    protected Label $value;

    protected Label $unit;

    protected Label $prompt;

    protected Label $near;

    protected Label $far;

    protected ProgressBar $bar;

    protected int $minMm;

    protected int $maxMm;

    public function __construct(int $minMm = 0, int $maxMm = 400)
    {
        parent::__construct('rangefinder-range-hud');

        $this->minMm = max(0, $minMm);
        $this->maxMm = max($this->minMm + 1, $maxMm);
        $this->title = Label::of('RANGEFINDER', Theme::color('muted'))->setAlign(TextAlign::CENTER);
        $this->value = Label::of('0', Theme::color('ink'))->setAlign(TextAlign::CENTER);
        $this->unit = Label::of('millimetres', Theme::color('accent'))->setAlign(TextAlign::CENTER);
        $this->prompt = Label::of('Place your hand over the sensor', Theme::color('accent'))
            ->setAlign(TextAlign::CENTER);
        $this->near = Label::of('NEAR '.$this->minMm.'mm', Theme::color('muted'));
        $this->far = Label::of($this->maxMm.'mm FAR', Theme::color('muted'))->setAlign(TextAlign::RIGHT);
        $this->bar = ProgressBar::of(0.0, Axis::HORIZONTAL);
        $this->bar->setColors(Theme::color('accent'), Theme::color('track'));

        foreach ([$this->title, $this->value, $this->unit, $this->prompt, $this->near, $this->far, $this->bar] as $child) {
            $this->addChild($child);
        }
    }

    public function sync(int $mm): void
    {
        $pct = $this->rangePercent($mm);
        $this->value->setText($mm < 0 ? '—' : (string) $mm);
        $this->bar->setValue($pct);

        if ($mm < 0) {
            $this->prompt->setText('Waiting for a reading…')->setColor(Theme::color('muted'));
        } elseif ($pct <= 0.20) {
            $this->prompt->setText('Pull your hand away from the sensor')->setColor(Theme::color('warning'));
        } elseif ($pct <= 0.55) {
            $this->prompt->setText('Move your hand closer — or farther away')->setColor(Theme::color('muted'));
        } else {
            $this->prompt->setText('Place your hand over the sensor')->setColor(Theme::color('accent'));
        }
    }

    protected function rangePercent(int $mm): float
    {
        if ($mm < 0) {
            return 0.0;
        }

        $span = max(1, $this->maxMm - $this->minMm);

        return max(0.0, min(1.0, ($mm - $this->minMm) / $span));
    }

    public function layout(Size $available): void
    {
        $w = max(1, $available->width);
        $h = max(1, $available->height);
        $this->setSize($w, $h);

        $marginX = max(16, (int) round($w * 0.05));
        $marginY = max(12, (int) round($h * 0.04));
        $innerW = max(1, $w - (2 * $marginX));
        $usable = max(1, $h - (2 * $marginY));

        $titleH = (int) round($usable * 0.10);
        $valueH = (int) round($usable * 0.42);
        $unitH = (int) round($usable * 0.08);
        $promptH = (int) round($usable * 0.14);
        $legendH = (int) round($usable * 0.06);
        $barH = max(12, min(28, (int) round($usable * 0.08)));

        $y = $marginY;
        $this->fitLabel($this->title, $innerW, $titleH, 1, 4);
        $this->centerChild($this->title, $marginX, $y, $innerW, $titleH);
        $y += $titleH;

        $this->fitLabel($this->value, $innerW, $valueH, 2, 48);
        $this->centerChild($this->value, $marginX, $y, $innerW, $valueH);
        $y += $valueH;

        $this->fitLabel($this->unit, $innerW, $unitH, 1, 6);
        $this->centerChild($this->unit, $marginX, $y, $innerW, $unitH);
        $y += $unitH;

        $this->fitLabel($this->prompt, $innerW, $promptH, 1, 5);
        $this->centerChild($this->prompt, $marginX, $y, $innerW, $promptH);

        $barY = $h - $marginY - $barH;
        $legendY = $barY - $legendH;
        $this->fitLabel($this->near, (int) ($innerW * 0.45), $legendH, 1, 3);
        $this->fitLabel($this->far, (int) ($innerW * 0.45), $legendH, 1, 3);
        $this->near->setPosition($marginX, $legendY + max(0, (int) (($legendH - $this->near->size()->height) / 2)));
        $this->far->setPosition(
            $marginX + $innerW - $this->far->size()->width,
            $legendY + max(0, (int) (($legendH - $this->far->size()->height) / 2)),
        );

        $this->bar->setThickness($barH);
        $this->bar->setPosition($marginX, $barY);
        $this->bar->setSize($innerW, $barH);
        $this->bar->layout(new Size($innerW, $barH));
    }

    protected function fitLabel(Label $label, int $maxW, int $maxH, int $min, int $max): void
    {
        $best = $min;
        for ($size = $max; $size >= $min; $size--) {
            $label->setTextSize($size);
            $label->layout($label->size());
            if ($label->size()->width <= $maxW && $label->size()->height <= $maxH) {
                $best = $size;
                break;
            }
        }
        $label->setTextSize($best);
        $label->layout($label->size());
    }

    protected function centerChild(Label $label, int $boxX, int $boxY, int $boxW, int $boxH): void
    {
        $x = $boxX + max(0, (int) (($boxW - $label->size()->width) / 2));
        $y = $boxY + max(0, (int) (($boxH - $label->size()->height) / 2));
        $label->setPosition($x, $y);
    }

    protected function draw(PaintContext $ctx): void
    {
        // children paint
    }
}
