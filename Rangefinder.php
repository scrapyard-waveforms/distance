<?php

namespace ScrapyardIO\Waveforms\Distance;

use Fabricate\Contracts\Sensors\Enums\DistanceUnit;
use Fabricate\Contracts\Sensors\SensorException;
use Fabricate\NutsAndBolts\MagicAliases\Circuit;
use Fabricate\Sensors\Sensor;
use Fabricate\Contracts\Sensors\Interfaces\Rangefinder as RangefinderCircuit;

class Rangefinder extends Sensor
{

    public function __construct(RangefinderCircuit $circuit)
    {
        parent::__construct($circuit);
    }

    public function distance(DistanceUnit $unit = DistanceUnit::MM): float
    {
        return $this->circuit->distance($unit);
    }

    public function within(array $range, DistanceUnit $unit, callable $callback): void
    {
        [$low, $high] = $range;

        while (true) {
            $value = $this->distance($unit);

            if ($value >= $low && $value <= $high) {
                if ($callback($value) === false) {
                    return;
                }
            }

            usleep(100_000);
        }
    }

    public function measure(int $num_readings = 1, DistanceUnit $unit = DistanceUnit::MM): ?ProximityData
    {
        if ($num_readings < 1) {
            return null;
        }

        $total = 0.0;
        for ($i = 0; $i < $num_readings; $i++) {
            $total += $this->distance($unit);
        }

        return new ProximityData($total / $num_readings, $unit, microtime(true));
    }

    /**
     * @throws SensorException
     */
    public static function circuit(string $driver): static
    {
        $circuit = Circuit::driver($driver);
        if($circuit instanceof RangefinderCircuit) {
            return new static($circuit);
        }

        throw new SensorException("Circuit [$driver] is not a Rangefinder.");
    }
}