<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

class ApiDocumentationServiceProvider extends ServiceProvider
{
  public function boot(): void
  {
    Scramble::configure()
      ->withDocumentTransformers(function (OpenApi $document): void {
        $document->secure(SecurityScheme::http('bearer'));
      });
  }
}
