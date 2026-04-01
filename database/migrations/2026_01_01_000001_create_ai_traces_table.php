<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_traces', function (Blueprint $table): void {
            $table->id();
            $table->uuid('trace_id')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('session_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('status')->default('ok')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable()->index();
            $table->unsignedInteger('total_input_tokens')->nullable();
            $table->unsignedInteger('total_output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('total_cost_usd', 12, 6)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_traces');
    }
};
