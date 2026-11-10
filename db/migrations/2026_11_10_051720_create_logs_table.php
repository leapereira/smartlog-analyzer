<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('logs', function (Blueprint $table) {
      $table->id()->generatedAs();
      $table->text('service');
      $table->text('level');
      $table->text('message');
      $table->timestampTz('timestamp');
      $table->timestampTz('created_at')->useCurrent();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('logs');
  }
};
