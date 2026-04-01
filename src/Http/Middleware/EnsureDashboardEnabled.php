<?php

namespace RyderAsKing\LaravelAiTrace\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('ai-trace.dashboard.enabled', true), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
