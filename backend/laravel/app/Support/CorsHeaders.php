<?php

namespace App\Support;

use Fruitcake\Cors\CorsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds CORS headers to rendered API error responses. The CORS middleware only
 * decorates the response returned by inner middleware, so an exception thrown
 * before or within the pipeline (throttling, oversized headers, malformed JSON)
 * reaches the browser without Access-Control-Allow-Origin. Mirroring the
 * middleware's actual-request headers keeps those failures readable cross-origin.
 */
final class CorsHeaders
{
    public static function apply(Response $response, Request $request): Response
    {
        $cors = new CorsService;
        $cors->setOptions((array) config('cors', []));

        return $cors->addActualRequestHeaders($response, $request);
    }
}
