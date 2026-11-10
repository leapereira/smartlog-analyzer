<?php

namespace App\Providers;

use App\Analyzers\MockLogAnalyzer;
use App\Contracts\LogAnalyzer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
  /**
   * Register any application services.
   */
  public function register(): void
  {
    $this->app->bind(LogAnalyzer::class, function (): LogAnalyzer {
      return match (config('services.smartlog.llm_driver')) {
        'mock' => new MockLogAnalyzer(),
        default => throw new InvalidArgumentException(
          'El proveedor de análisis configurado no está implementado.',
        ),
      };
    });
  }

  /**
   * Bootstrap any application services.
   */
  public function boot(): void
  {
    RateLimiter::for('api', function (Request $request) {
      return Limit::perMinute(
        (int) config('services.smartlog.api_rate_limit_per_minute', 60),
      )->by($request->ip());
    });
  }
}
