<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ApiDocumentationServiceProvider extends ServiceProvider
{
  public function boot(): void
  {
    Scramble::configure()
      ->expose(
        ui: fn (Router $router, $action) => $router
          ->get('docs/api/v1', $action)
          ->name('scramble.docs.ui'),
        document: fn (Router $router, $action) => $router
          ->get('docs/api/v1.json', $action)
          ->name('scramble.docs.document'),
      )
      ->withDocumentTransformers(function (OpenApi $document): void {
        $document->secure(SecurityScheme::http('bearer'));
      });
  }
}
