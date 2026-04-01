<?php

namespace RyderAsKing\LaravelAiTrace\Facades;

use Illuminate\Support\Facades\Facade;

class AiTrace extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'ai-trace';
    }
}
