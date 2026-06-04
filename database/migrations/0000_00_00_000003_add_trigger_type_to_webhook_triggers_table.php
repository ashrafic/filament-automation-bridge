<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_triggers', function (Blueprint $table) {
            $table->string('trigger_type', 50)->default('model-event')->after('event');
            $table->json('trigger_config')->nullable()->after('custom_payload_template');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_triggers', function (Blueprint $table) {
            $table->dropColumn('trigger_type');
            $table->dropColumn('trigger_config');
        });
    }
};
