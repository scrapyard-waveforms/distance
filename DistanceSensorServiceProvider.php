<?php

namespace ScrapyardIO\Waveforms\Distance;

use Fabricate\NutsAndBolts\ServiceProvider;
use ScrapyardIO\Waveforms\Distance\Rangefinder;
use Fabricate\NutsAndBolts\MagicAliases\Sensor;
use Fabricate\Contracts\Chassis\CircularDependencyException;

class DistanceSensorServiceProvider extends ServiceProvider
{
    public function register(): void {}

    /**
     * @throws CircularDependencyException
     */
    protected function enabled(): bool
    {
        return config('waveforms.rangefinder.enabled', false);
    }

    /**
     * @throws CircularDependencyException
     */
    public function boot(): void {
        if($this->enabled()) {
            Sensor::addSensor('rangefinder', Rangefinder::class);
        }
    }
}