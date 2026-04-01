<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_span_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trace_id')->constrained('ai_traces')->cascadeOnDelete();
            $table->foreignId('span_id')->constrained('ai_spans')->cascadeOnDelete();
            $table->string('event_type')->index();
            $table->json('payload')->nullable();
            $table->timestamp('recorded_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_span_events');
    }
};
