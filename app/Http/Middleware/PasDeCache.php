<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages du back-office : jamais gardées par le navigateur, un proxy ou le service worker.
 */
class PasDeCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);
        $reponse->headers->set('Cache-Control', 'no-store, private');

        return $reponse;
    }
}
