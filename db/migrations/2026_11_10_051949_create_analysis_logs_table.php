<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('analysis_logs', function (Blueprint $table) {
      $table->foreignId('analysis_id')
        ->constrained('analyses')
        ->cascadeOnDelete();

      $table->foreignId('log_id')
        ->constrained('logs')
        ->restrictOnDelete();

      $table->primary(['analysis_id', 'log_id']);
      $table->index('log_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('analysis_logs');
  }
};
