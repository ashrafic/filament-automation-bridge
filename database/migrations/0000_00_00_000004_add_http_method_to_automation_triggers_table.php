<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_triggers', function (Blueprint $table) {
            $table->string('http_method', 10)->default('POST')->after('destination_url');
        });

        Schema::table('automation_deliveries', function (Blueprint $table) {
            $table->string('http_method', 10)->nullable()->after('destination_url');
        });
    }

    public function down(): void
    {
        Schema::table('automation_triggers', function (Blueprint $table) {
            $table->dropColumn('http_method');
        });

        Schema::table('automation_deliveries', function (Blueprint $table) {
            $table->dropColumn('http_method');
        });
    }
};
