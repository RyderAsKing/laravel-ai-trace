<?php

namespace RyderAsKing\LaravelAiTrace\Support;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class DashboardAssets
{
    /**
     * @var list<string|Htmlable>
     */
    protected array $css = [__DIR__.'/../../dist/ai-trace.css'];

    /**
     * @var list<string|Htmlable>
     */
    protected array $js = [__DIR__.'/../../dist/ai-trace.js'];

    /**
     * @param  string|Htmlable|list<string|Htmlable>|null  $css
     */
    public function css(string|Htmlable|array|null $css = null): string|self
    {
        if (func_num_args() === 1) {
            $this->css = array_values(array_unique(array_merge($this->css, Arr::wrap($css)), SORT_REGULAR));

            return $this;
        }

        return $this->compileStyles($this->css);
    }

    /**
     * @param  string|Htmlable|list<string|Htmlable>|null  $js
     */
    public function js(string|Htmlable|array|null $js = null): string|self
    {
        if (func_num_args() === 1) {
            $this->js = array_values(array_unique(array_merge($this->js, Arr::wrap($js)), SORT_REGULAR));

            return $this;
        }

        return $this->compileScripts($this->js);
    }

    /**
     * @param  list<string|Htmlable>  $styles
     */
    protected function compileStyles(array $styles): string
    {
        return collect($styles)->reduce(function (string $carry, string|Htmlable $style): string {
            if ($style instanceof Htmlable) {
                return $carry.Str::finish($style->toHtml(), PHP_EOL);
            }

            $contents = @file_get_contents($style);

            if ($contents === false) {
                throw new RuntimeException("Unable to load AI Trace dashboard CSS path [{$style}].");
            }

            return $carry."<style>{$contents}</style>".PHP_EOL;
        }, '');
    }

    /**
     * @param  list<string|Htmlable>  $scripts
     */
    protected function compileScripts(array $scripts): string
    {
        return collect($scripts)->reduce(function (string $carry, string|Htmlable $script): string {
            if ($script instanceof Htmlable) {
                return $carry.Str::finish($script->toHtml(), PHP_EOL);
            }

            $contents = @file_get_contents($script);

            if ($contents === false) {
                throw new RuntimeException("Unable to load AI Trace dashboard JavaScript path [{$script}].");
            }

            return $carry."<script>{$contents}</script>".PHP_EOL;
        }, '');
    }
}
