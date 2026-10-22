<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSaaSEdition
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('wabantu.edition') !== 'saas') {
            return response()->json([
                'type' => 'https://wabantu.local/problems/edition',
                'title' => 'SaaS edition required',
                'status' => 404,
                'detail' => 'Platform APIs are unavailable when APP_EDITION=standalone.',
            ], 404);
        }

        return $next($request);
    }
}
