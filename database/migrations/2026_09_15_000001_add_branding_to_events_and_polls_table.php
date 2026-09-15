<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Branding hängt am Link-Besitzer: Event, bzw. Poll bei Umfragen ohne Event
        foreach (['events', 'polls'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('brand_logo_path')->nullable();
                $table->string('brand_primary_color', 7)->nullable();
                $table->string('brand_accent_color', 7)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['events', 'polls'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['brand_logo_path', 'brand_primary_color', 'brand_accent_color']);
            });
        }
    }
};
