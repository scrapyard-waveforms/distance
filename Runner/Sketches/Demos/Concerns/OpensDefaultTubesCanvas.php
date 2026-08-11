<?php

namespace Waveforms\Distance\Runner\Sketches\Demos\Concerns;

use InvalidArgumentException;
use ScrapyardIO\Tubes\Canvas\Canvas;
use ScrapyardIO\Tubes\Canvas\OSWindow;
use ScrapyardIO\Tubes\Canvas\PanelIC;
use ScrapyardIO\Tubes\Core\Enums\CanvasProfileKind;
use ScrapyardIO\Tubes\Core\MagicAliases\Panel;
use ScrapyardIO\Tubes\Core\MagicAliases\Window;
use ScrapyardIO\Tubes\Core\Support\CanvasProfiles;
use ScrapyardIO\Tubes\Panels\PanelException;
use ScrapyardIO\Tubes\Rendering\Renderer2D;
use ScrapyardIO\Tubes\Windows\WindowException;
use Throwable;

/**
 * Open tubes.defaults.canvas — any windows.* or panels.* profile.
 *
 * @mixin \Fabricate\Sketches\Sketch
 */
trait OpensDefaultTubesCanvas
{
    protected ?Canvas $canvas = null;

    protected ?Renderer2D $renderer = null;

    protected ?string $canvasProfile = null;

    /**
     * @return bool false when the sketch should quit
     */
    protected function bootDefaultTubesCanvas(): bool
    {
        $slug = trim((string) config('tubes.defaults.canvas', ''));
        if ($slug === '') {
            $this->error(
                'tubes.defaults.canvas is empty. Set a windows.* or panels.* slug in config/tubes.php.'
            );

            return false;
        }

        try {
            [$kind] = CanvasProfiles::locate($slug);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return false;
        }

        try {
            if ($kind === CanvasProfileKind::PANELS) {
                $this->canvas = Panel::profile($slug);
                $this->renderer = null;
            } else {
                $pending = Window::profile($slug);
                $this->renderer = $this->resolveWindowRenderer($pending->driver());
                $this->canvas = $pending->open();
            }
            $this->canvasProfile = $slug;
        } catch (PanelException|WindowException|Throwable $e) {
            $this->error($e->getMessage());
            $this->canvas = null;
            $this->renderer = null;

            return false;
        }

        return true;
    }

    protected function canvasRenderer(): Renderer2D
    {
        if ($this->canvas instanceof PanelIC) {
            return $this->canvas->renderer();
        }

        if (is_null($this->renderer)) {
            throw new \RuntimeException('Window canvas renderer is not initialized.');
        }

        return $this->renderer;
    }

    protected function defaultCanvasShouldStop(): bool
    {
        if (! $this->canvas instanceof OSWindow) {
            return false;
        }

        $this->canvas->pollEvents();

        return $this->canvas->shouldClose();
    }

    protected function closeDefaultTubesCanvas(): void
    {
        if (! is_null($this->canvas)) {
            try {
                if ($this->canvas instanceof OSWindow) {
                    $this->renderer?->unsetFramebuffer();
                }
                $this->canvas->close();
            } catch (Throwable) {
                //
            }
        }
        $this->canvas = null;
        $this->renderer = null;
        $this->canvasProfile = null;
    }

    protected function resolveWindowRenderer(string $driver): Renderer2D
    {
        /** @var array<string, class-string<Renderer2D>> $map */
        $map = [
            'metal' => 'Microscrap\\GFX\\Metal\\MetalRenderer2D',
            'open-gl' => 'Microscrap\\GFX\\OGX\\OpenGLRenderer2D',
            'vulkan' => 'Microscrap\\GFX\\Vulkan\\VulkanRenderer2D',
            'cuda' => 'Microscrap\\GFX\\CUDA\\CudaGPURenderer2D',
            'sdl3' => 'Microscrap\\GFX\\SDL3\\SDL3Renderer2D',
        ];

        $class = $map[$driver] ?? null;
        if (! is_null($class) && class_exists($class)) {
            return new $class;
        }

        throw new \RuntimeException(
            "Canvas window driver [{$driver}] requires its engine Renderer2D companion."
        );
    }
}
