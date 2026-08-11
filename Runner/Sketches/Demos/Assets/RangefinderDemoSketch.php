<?php

namespace Waveforms\Distance\Runner\Sketches\Demos\Assets;

/**
 * Workshop sketch slugs for chip-agnostic Rangefinder demos.
 */
enum RangefinderDemoSketch: string
{
    case OLED = 'rangefinder-oled-demo';
    case CANVAS = 'rangefinder-canvas-demo';
    case UX_ALIAS = 'rangefinder-ux-canvas-demo';
}
