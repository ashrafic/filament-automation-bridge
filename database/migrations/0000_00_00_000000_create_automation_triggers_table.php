<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('model_class', 512);
            $table->string('event', 50);
            $table->string('destination_type', 50);
            $table->string('destination_url', 2048);
            $table->json('field_mapping');
            $table->string('payload_mode', 20)->default('summary');
            $table->text('custom_payload_template')->nullable();
            $table->json('conditions')->nullable();
            $table->text('secret')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('request_timeout')->default(5);
            $table->unsignedInteger('max_retries')->default(3);
            $table->json('ip_whitelist')->nullable();
            $table->boolean('encrypt_payload')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['model_class', 'event'], 'idx_triggers_model_event');
            $table->index('active', 'idx_triggers_active');
            $table->index('destination_type', 'idx_triggers_dest_type');
            $table->index('created_at', 'idx_triggers_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_triggers');
    }
};
