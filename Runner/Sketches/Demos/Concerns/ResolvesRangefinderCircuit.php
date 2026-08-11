<?php

namespace Waveforms\Distance\Runner\Sketches\Demos\Concerns;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Throwable;
use Waveforms\Distance\Rangefinder;

/**
 * Require a circuits.php profile argument and open {@see Rangefinder}.
 *
 * @mixin \Fabricate\Sketches\Sketch
 */
trait ResolvesRangefinderCircuit
{
    protected ?string $circuitProfile = null;

    protected ?Rangefinder $rangefinder = null;

    protected bool $stopRequested = false;

    protected function configureRangefinderProfileArgument(Command $command): void
    {
        $command->addArgument(
            'profile',
            InputArgument::REQUIRED,
            'circuits.php profile name (ic must MeasureDistance)',
        );
    }

    protected function installStopHandlers(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);
        $stop = function (): void {
            $this->stopRequested = true;
        };
        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);
    }

    /**
     * @return bool false when the sketch should quit (errors already printed)
     */
    protected function bootRangefinder(): bool
    {
        $requested = $this->argument('profile');
        if (! is_string($requested) || trim($requested) === '') {
            $this->error('Profile argument is required.');

            return false;
        }

        $this->circuitProfile = trim($requested);

        try {
            $this->rangefinder = Rangefinder::circuit($this->circuitProfile);
            $this->syncRangefinderScale();
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->rangefinder = null;
            $this->circuitProfile = null;

            return false;
        }

        return true;
    }

    /**
     * Pull chip {@see \Waveforms\Contracts\Distance\MinDistance}/{@see \Waveforms\Contracts\Distance\MaxDistance} into sketch bar scale.
     */
    protected function syncRangefinderScale(): void
    {
        if (is_null($this->rangefinder)) {
            return;
        }

        $range = $this->rangefinder->distanceRange();
        if (property_exists($this, 'rangeMinMm')) {
            $this->rangeMinMm = (int) round($range['min']);
        }
        if (property_exists($this, 'rangeMaxMm')) {
            $this->rangeMaxMm = max(
                (property_exists($this, 'rangeMinMm') ? $this->rangeMinMm : 0) + 1,
                (int) round($range['max']),
            );
        }
    }

    protected function closeRangefinder(): void
    {
        $this->rangefinder = null;
        $this->circuitProfile = null;
    }
}
