<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_spans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trace_id')->constrained('ai_traces')->cascadeOnDelete();
            $table->uuid('span_id')->unique();
            $table->string('parent_span_id')->nullable()->index();
            $table->string('span_type')->index();
            $table->string('name')->nullable()->index();
            $table->string('source')->default('ai_sdk')->index();
            $table->string('provider')->nullable()->index();
            $table->string('model_raw')->nullable();
            $table->string('model_normalized')->nullable()->index();
            $table->longText('input_text')->nullable();
            $table->longText('output_text')->nullable();
            $table->string('input_hash')->nullable();
            $table->string('output_hash')->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('input_cost_usd', 12, 6)->nullable();
            $table->decimal('output_cost_usd', 12, 6)->nullable();
            $table->decimal('total_cost_usd', 12, 6)->nullable();
            $table->string('status')->default('ok')->index();
            $table->string('error_type')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_spans');
    }
};
