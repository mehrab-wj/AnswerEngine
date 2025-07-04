<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class ForceSSL
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('X-Forwarded-Proto')) {
            if (strcmp($request->header('X-Forwarded-Proto'), 'https') === 0) {
                return $next($request);
            }
        }

        if (!$request->secure() && app()->environment(['staging', 'production'])) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
