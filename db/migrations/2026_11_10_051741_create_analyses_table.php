<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    DB::statement(
      "CREATE TYPE analysis_status AS ENUM ('processing', 'completed', 'failed')"
    );

    Schema::create('analyses', function (Blueprint $table) {
      $table->id()->generatedAs();
      $table->rawColumn('status', 'analysis_status')->default('processing');
      $table->jsonb('result')->nullable();
      $table->text('error')->nullable();
      $table->timestampTz('created_at')->useCurrent();
      $table->timestampTz('updated_at')->useCurrent();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('analyses');
    DB::statement('DROP TYPE IF EXISTS analysis_status');
  }
};
