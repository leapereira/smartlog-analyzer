<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalEnvironment
{
  public function handle(Request $request, Closure $next): Response
  {
    abort_unless(app()->environment('local'), Response::HTTP_FORBIDDEN);

    return $next($request);
  }
}
