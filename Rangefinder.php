<?php

namespace Waveforms\Distance;

use GeneralPurposeIO\Core\MagicAliases\Circuit;
use ReflectionClass;
use Waveforms\Contracts\Distance\DistanceUnit;
use Waveforms\Contracts\Distance\MaxDistance;
use Waveforms\Contracts\Distance\MeasuresDistance;
use Waveforms\Contracts\Distance\MinDistance;
use Waveforms\Contracts\Sensors\SensorException;
use Waveforms\PhysicalDevices\AbstractSensor;

class Rangefinder extends AbstractSensor
{
    public function __construct(
        protected MeasuresDistance $sensor
    ) {}

    public function distance(DistanceUnit $unit = DistanceUnit::MM): float
    {
        return $this->sensor->distance($unit);
    }

    /**
     * Minimum measurable distance from the chip's {@see MinDistance} property.
     */
    public function minDistance(DistanceUnit $unit = DistanceUnit::MM): float
    {
        return $unit->convertFromMm($this->attributedDistanceMm(MinDistance::class, 0.0));
    }

    /**
     * Maximum measurable distance from the chip's {@see MaxDistance} property.
     */
    public function maxDistance(DistanceUnit $unit = DistanceUnit::MM): float
    {
        return $unit->convertFromMm($this->attributedDistanceMm(MaxDistance::class, 4000.0));
    }

    /**
     * @return array{min: float, max: float}
     */
    public function distanceRange(DistanceUnit $unit = DistanceUnit::MM): array
    {
        $min = $this->minDistance($unit);
        $max = $this->maxDistance($unit);

        if ($max <= $min) {
            $max = $min + $unit->convertFromMm(1.0);
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * Read the first property tagged with $attributeClass on the wrapped sensor.
     *
     * @param  class-string  $attributeClass
     */
    protected function attributedDistanceMm(string $attributeClass, float $defaultMm): float
    {
        $reflection = new ReflectionClass($this->sensor);

        do {
            foreach ($reflection->getProperties() as $property) {
                if ($property->getAttributes($attributeClass) === []) {
                    continue;
                }

                $property->setAccessible(true);
                if (! $property->isInitialized($this->sensor)) {
                    $defaults = $reflection->getDefaultProperties();
                    $value = $defaults[$property->getName()] ?? $defaultMm;
                } else {
                    $value = $property->getValue($this->sensor);
                }

                if (is_numeric($value)) {
                    return (float) $value;
                }
            }
        } while ($reflection = $reflection->getParentClass());

        return $defaultMm;
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

    public static function circuit(string $driver): static
    {
        $circuit = Circuit::profile($driver);
        if($circuit instanceof MeasuresDistance) {
            return new static($circuit);
        }

        throw new SensorException("Circuit [$driver] does not Measure Distance.");
    }
}
