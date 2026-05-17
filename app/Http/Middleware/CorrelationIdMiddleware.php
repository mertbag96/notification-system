<?php

namespace App\Http\Middleware;

use App\Support\CorrelationId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorrelationIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header(CorrelationId::HEADER, '');
        $correlationId = $header !== '' ? $header : CorrelationId::generate();

        CorrelationId::set($correlationId);
        $request->headers->set(CorrelationId::HEADER, $correlationId);

        $response = $next($request);
        $response->headers->set(CorrelationId::HEADER, $correlationId);

        return $response;
    }
}
