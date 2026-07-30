<?php

namespace ScrapyardIO\Waveforms\Distance;

use Fabricate\Contracts\Sensors\Enums\DistanceUnit;
use Fabricate\Contracts\Sensors\Measurements\DistanceEvent;

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
