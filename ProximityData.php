<?php

namespace Waveforms\Distance;

use Waveforms\Contracts\Distance\DistanceEvent;
use Waveforms\Contracts\Distance\DistanceUnit;

class ProximityData extends DistanceEvent
{
    public function __construct(
        int|float $value,
        DistanceUnit $unit,
        int|float $timestamp
    ) {
        parent::__construct($value, $unit, $timestamp);
    }
}
