<?php

namespace RyderAsKing\LaravelAiTrace\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = (string) config('ai-trace.dashboard.authorization_gate', 'viewAiTrace');

        if (Gate::has($gate)) {
            abort_unless(Gate::allows($gate), Response::HTTP_FORBIDDEN);

            return $next($request);
        }

        abort_unless(app()->environment('local'), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
