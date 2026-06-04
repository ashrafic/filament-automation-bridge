<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_builtin')->default(false);
            $table->string('model_class', 512);
            $table->string('event', 50);
            $table->string('destination_type', 50);
            $table->json('field_mapping');
            $table->string('payload_mode', 20)->default('summary');
            $table->text('custom_payload_template')->nullable();
            $table->json('conditions')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('is_builtin', 'idx_templates_builtin');
            $table->index(['model_class', 'event'], 'idx_templates_model_event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_templates');
    }
};