<?php

namespace Waveforms\Distance\Runner\Sketches\Demos;

use Fabricate\Contracts\Sketches\Attributes\Sketch as SketchAttribute;
use Fabricate\Contracts\Sketches\SketchLoopResult;
use Fabricate\Sketches\Sketch;
use ScrapyardIO\Tubes\Panels\MonochromePanel;
use Symfony\Component\Console\Command\Command;
use Throwable;
use Waveforms\Distance\Runner\Sketches\Demos\Concerns\OpensDefaultTubesCanvas;
use Waveforms\Distance\Runner\Sketches\Demos\Concerns\PaintsTubesDistanceHud;
use Waveforms\Distance\Runner\Sketches\Demos\Concerns\ResolvesRangefinderCircuit;

/**
 * Rangefinder on tubes.defaults.canvas (window or non-mono panel).
 *
 *   ./runner rangefinder-canvas-demo vl53l1x
 *
 * When scrapyard-io/ux is installed, {@see UXCanvasTestSketch} replaces this slug.
 * MonochromePanel is rejected — use rangefinder-oled-demo instead.
 */
#[SketchAttribute('rangefinder-canvas-demo')]
class CanvasTestSketch extends Sketch
{
    use ResolvesRangefinderCircuit;
    use OpensDefaultTubesCanvas;
    use PaintsTubesDistanceHud;

    protected string $description = 'Rangefinder distance + bar on tubes.defaults.canvas (Ctrl-C to stop)';

    protected bool $announced = false;

    protected int $lastSampleNs = 0;

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
                "Canvas demo rejects MonochromePanel [{$this->canvasProfile}]. "
                .'Use rangefinder-oled-demo instead.'
            );
            $this->closeDefaultTubesCanvas();
            $this->closeRangefinder();
        }
    }

    public function loop(): SketchLoopResult
    {
        if ($this->stopRequested || $this->defaultCanvasShouldStop()) {
            $this->info('Rangefinder canvas demo stopped.');

            return SketchLoopResult::STOP;
        }

        if (is_null($this->rangefinder) || is_null($this->canvas) || $this->canvas instanceof MonochromePanel) {
            return SketchLoopResult::STOP;
        }

        if (! $this->announced) {
            $this->info(
                "Rangefinder canvas via Rangefinder::circuit('{$this->circuitProfile}') → canvas [{$this->canvasProfile}]"
            );
            $this->line('  Distance mm + bar — Ctrl-C to end.');
            $this->announced = true;
        }

        $now = hrtime(true);
        if ($this->lastSampleNs !== 0 && ($now - $this->lastSampleNs) < 300_000_000) {
            usleep(2_000);

            return SketchLoopResult::CONTINUE;
        }

        try {
            $mm = (int) round($this->rangefinder->distance());
            $renderer = $this->canvasRenderer();
            $this->paintDistanceHud($renderer, $this->canvas, $mm);
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
        $this->closeDefaultTubesCanvas();
        $this->closeRangefinder();
    }
}
