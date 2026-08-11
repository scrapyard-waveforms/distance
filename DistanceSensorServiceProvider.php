<?php

namespace Waveforms\Distance;

use Fabricate\Contracts\Sketches\SketchRegistry;
use Fabricate\NutsAndBolts\ServiceProvider;
use Waveforms\Distance\Runner\Sketches\Demos\Assets\RangefinderDemoSketch;
use Waveforms\Distance\Runner\Sketches\Demos\CanvasTestSketch;
use Waveforms\Distance\Runner\Sketches\Demos\OLEDTestSketch;
use Waveforms\Distance\Runner\Sketches\Demos\UXCanvasTestSketch;

class DistanceSensorServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->registerDemoSketches();
    }

    protected function registerDemoSketches(): void
    {
        if (! $this->container->bound(SketchRegistry::class)) {
            return;
        }

        // Soft tubes dependency.
        if (! class_exists(\ScrapyardIO\Tubes\Core\MagicAliases\Panel::class)) {
            return;
        }

        /** @var SketchRegistry $registry */
        $registry = $this->container->make(SketchRegistry::class);

        if (! $registry->has(RangefinderDemoSketch::OLED->value)) {
            $registry->registerConvention(RangefinderDemoSketch::OLED->value, OLEDTestSketch::class);
        }

        if (! $registry->has(RangefinderDemoSketch::CANVAS->value)) {
            $registry->registerConvention(RangefinderDemoSketch::CANVAS->value, CanvasTestSketch::class);
        }

        if (class_exists(\ScrapyardIO\UX\Core\Scene::class)) {
            $registry->replace(UXCanvasTestSketch::class);

            if (! $registry->has(RangefinderDemoSketch::UX_ALIAS->value)) {
                $registry->registerConvention(
                    RangefinderDemoSketch::UX_ALIAS->value,
                    UXCanvasTestSketch::class,
                );
            }
        }
    }
}
