<?php

namespace Waveforms\Distance\Runner\Sketches\Demos;

use Fabricate\Contracts\Sketches\Attributes\Sketch as SketchAttribute;
use Fabricate\Contracts\Sketches\SketchLoopResult;
use Fabricate\Sketches\Sketch;
use ScrapyardIO\Tubes\Panels\MonochromePanel;
use ScrapyardIO\UX\Core\Scene;
use ScrapyardIO\UX\Geometry\Size;
use ScrapyardIO\UX\Support\Theme;
use Symfony\Component\Console\Command\Command;
use Throwable;
use Waveforms\Distance\Runner\Sketches\Demos\Assets\RangeHud;
use Waveforms\Distance\Runner\Sketches\Demos\Concerns\OpensDefaultTubesCanvas;
use Waveforms\Distance\Runner\Sketches\Demos\Concerns\ResolvesRangefinderCircuit;

/**
 * Rangefinder on a UX Scene (binds over {@see CanvasTestSketch}).
 *
 * Same slug: rangefinder-canvas-demo. Alias: rangefinder-ux-canvas-demo.
 * MonochromePanel rejected — use rangefinder-oled-demo.
 */
#[SketchAttribute('rangefinder-canvas-demo')]
class UXCanvasTestSketch extends Sketch
{
    use ResolvesRangefinderCircuit;
    use OpensDefaultTubesCanvas;

    protected string $description = 'Rangefinder distance + bar via UX Scene on tubes.defaults.canvas (Ctrl-C to stop)';

    protected bool $announced = false;

    protected int $lastSampleNs = 0;

    protected int $rangeMinMm = 0;

    protected int $rangeMaxMm = 400;

    protected ?Scene $scene = null;

    protected ?RangeHud $hud = null;

    public function configureCommand(Command $command): void
    {
        $this->configureRangefinderProfileArgument($command);
    }

    public function boot(): void
    {
        $this->installStopHandlers();

        if (! $this->bootRangefinder()) {
            return;
        }

        if (! $this->bootDefaultTubesCanvas()) {
            return;
        }

        if ($this->canvas instanceof MonochromePanel) {
            $this->error(
                "UX canvas demo rejects MonochromePanel [{$this->canvasProfile}]. "
                .'Use rangefinder-oled-demo instead.'
            );
            $this->closeDefaultTubesCanvas();
            $this->closeRangefinder();

            return;
        }

        $this->hud = new RangeHud($this->rangeMinMm, $this->rangeMaxMm);
        $this->scene = (new Scene)
            ->attach($this->canvas)
            ->setRoot($this->hud)
            ->setClearColor(Theme::color('surface'));

        $size = new Size($this->canvas->width(), $this->canvas->height());
        $this->hud->layout($size);
    }

    public function loop(): SketchLoopResult
    {
        if ($this->stopRequested || $this->defaultCanvasShouldStop()) {
            $this->info('Rangefinder UX canvas demo stopped.');

            return SketchLoopResult::STOP;
        }

        if (
            is_null($this->rangefinder)
            || is_null($this->canvas)
            || is_null($this->scene)
            || is_null($this->hud)
            || $this->canvas instanceof MonochromePanel
        ) {
            return SketchLoopResult::STOP;
        }

        if (! $this->announced) {
            $this->info(
                "Rangefinder UX canvas via Rangefinder::circuit('{$this->circuitProfile}') → canvas [{$this->canvasProfile}]"
            );
            $this->line('  UX Scene HUD — Ctrl-C to end.');
            $this->announced = true;
        }

        $now = hrtime(true);
        if ($this->lastSampleNs !== 0 && ($now - $this->lastSampleNs) < 300_000_000) {
            usleep(2_000);

            return SketchLoopResult::CONTINUE;
        }

        try {
            $mm = (int) round($this->rangefinder->distance());
            $this->hud->sync($mm);
            $renderer = $this->canvasRenderer();
            $fb = $this->canvas->framebuffer();
            $renderer->setFramebuffer($fb);
            $this->scene->paint($renderer);
            $this->canvas->present();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return SketchLoopResult::STOP;
        }

        $this->lastSampleNs = $now;

        return SketchLoopResult::CONTINUE;
    }

    public function shutdown(): void
    {
        $this->scene = null;
        $this->hud = null;
        $this->closeDefaultTubesCanvas();
        $this->closeRangefinder();
    }
}
