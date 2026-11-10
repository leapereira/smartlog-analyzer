<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
  public function handle(Request $request, Closure $next): Response
  {
    $expectedKey = config('services.smartlog.api_key');
    $providedKey = $request->bearerToken();

    if (
      !is_string($expectedKey)
      || $expectedKey === ''
      || !is_string($providedKey)
      || !hash_equals($expectedKey, $providedKey)
    ) {
      return response()->json(['message' => 'Unauthenticated.'], 401);
    }

    return $next($request);
  }
}
