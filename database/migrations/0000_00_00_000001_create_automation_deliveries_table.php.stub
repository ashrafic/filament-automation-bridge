<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('trigger_id')->constrained('automation_triggers')->cascadeOnDelete();
            $table->nullableMorphs('model');
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->json('response_headers')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->longText('response_body')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('max_retries')->default(3);
            $table->string('source', 30)->default('realtime');
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('uuid', 'idx_deliveries_uuid');
            $table->index(['trigger_id', 'status'], 'idx_deliveries_trigger_status');
            $table->index('status', 'idx_deliveries_status');
            $table->index(['model_type', 'model_id'], 'idx_deliveries_model');
            $table->index('created_at', 'idx_deliveries_created');
            $table->index('source', 'idx_deliveries_source');
            $table->index('http_status', 'idx_deliveries_http_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_deliveries');
    }
};
